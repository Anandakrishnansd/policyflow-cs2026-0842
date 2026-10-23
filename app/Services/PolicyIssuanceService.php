<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Models\IssuanceAttempt;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PolicyIssuanceService
{
    public function __construct(
        protected MockInsurerService $mockInsurer
    ) {}

    /**
     * Submit policy to insurer or safely resume issuance.
     * Guards against duplicate provider calls if already issued.
     */
    public function submit(PolicyRequest $policy, string $scenario = 'SUCCESS'): array
    {
        // 1. Guard against repeated calls on already completed policies
        if ($policy->status === PolicyStatus::ISSUED) {
            return [
                'status' => 'already_issued',
                'message' => 'Policy is already ISSUED. Submission skipped to prevent duplicate issuance.',
                'policy_number' => $policy->policy_number,
                'policy' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
            ];
        }

        // 2. Prevent blind re-submission if marked as needing reconciliation
        if ($policy->status === PolicyStatus::NEEDS_RECONCILIATION) {
            // First reconcile to see if the previous timeout actually created the policy
            $reconciliation = $this->reconcile($policy);
            if ($policy->fresh()->status === PolicyStatus::ISSUED) {
                return [
                    'status' => 'reconciled_to_issued',
                    'message' => 'Policy was previously issued on provider ledger and has now been reconciled safely.',
                    'policy_number' => $policy->policy_number,
                    'policy' => $policy->fresh(['stateHistories', 'issuanceAttempts']),
                ];
            }
        }

        // 3. Concurrency / State transition to SUBMITTED
        return DB::transaction(function () use ($policy, $scenario) {
            // Refresh with lock
            $lockedPolicy = PolicyRequest::where('id', $policy->id)->lockForUpdate()->first();

            if ($lockedPolicy->status === PolicyStatus::ISSUED) {
                return [
                    'status' => 'already_issued',
                    'message' => 'Policy is already ISSUED.',
                    'policy_number' => $lockedPolicy->policy_number,
                    'policy' => $lockedPolicy,
                ];
            }

            // Transition from VALIDATED, FAILED, or NEEDS_RECONCILIATION to SUBMITTED
            $lockedPolicy->attempt_count += 1;
            $lockedPolicy->transitionTo(
                PolicyStatus::SUBMITTED,
                "Issuance attempt #{$lockedPolicy->attempt_count} dispatched to mock insurer (Scenario: {$scenario})",
                ['scenario' => $scenario, 'attempt' => $lockedPolicy->attempt_count]
            );

            // 4. Call Mock Insurer
            $response = $this->mockInsurer->issuePolicy($lockedPolicy, $scenario);

            // 5. Record attempt audit log
            IssuanceAttempt::create([
                'policy_request_id' => $lockedPolicy->id,
                'attempt_number' => $lockedPolicy->attempt_count,
                'scenario' => $scenario,
                'request_payload' => [
                    'client_request_id' => $lockedPolicy->client_request_id,
                    'agent_id' => $lockedPolicy->agent_id,
                    'plan_code' => $lockedPolicy->plan_code,
                    'premium_amount' => $lockedPolicy->premium_amount,
                ],
                'response_payload' => $response,
                'http_status' => $response['http_status'] ?? 500,
                'outcome' => $response['success'] ? 'SUCCESS' : (! empty($response['timed_out']) ? 'TIMED_OUT' : 'FAILED'),
                'error_message' => $response['error'] ?? null,
            ]);

            // 6. Handle Outcomes safely
            if (! empty($response['timed_out'])) {
                // TIMEOUT_AFTER_ISSUE:
                // Insurer did not respond in time. Do not blindly reissue.
                if (! empty($response['provider_reference'])) {
                    $lockedPolicy->provider_reference = $response['provider_reference'];
                }
                $lockedPolicy->last_error = $response['error'] ?? 'Network timeout during issuance.';
                $lockedPolicy->transitionTo(
                    PolicyStatus::NEEDS_RECONCILIATION,
                    'Network timeout encountered. State flagged for provider reconciliation to prevent duplicate issuance.',
                    ['provider_reference' => $response['provider_reference'] ?? null]
                );

                return [
                    'status' => 'timed_out',
                    'message' => 'Timeout received from provider. Flagged for status reconciliation.',
                    'policy' => $lockedPolicy->fresh(['stateHistories', 'issuanceAttempts']),
                ];
            }

            if (! $response['success']) {
                // TEMPORARY_FAILURE:
                $lockedPolicy->last_error = $response['error'] ?? 'External provider error.';
                $lockedPolicy->transitionTo(
                    PolicyStatus::FAILED,
                    'Insurer returned temporary error. Request eligible for safe retry.',
                    ['error' => $response['error'] ?? 'Provider error']
                );

                return [
                    'status' => 'failed',
                    'message' => 'Insurer rejected or failed request. Original premium preserved for retry.',
                    'error' => $lockedPolicy->last_error,
                    'policy' => $lockedPolicy->fresh(['stateHistories', 'issuanceAttempts']),
                ];
            }

            // DELAYED_CALLBACK:
            if (($response['state'] ?? null) === 'PENDING_CONFIRMATION') {
                $lockedPolicy->provider_reference = $response['provider_reference'];
                $lockedPolicy->transitionTo(
                    PolicyStatus::PENDING_CONFIRMATION,
                    'Insurer accepted submission. Awaiting async confirmation callback.',
                    ['provider_reference' => $response['provider_reference']]
                );

                return [
                    'status' => 'pending_confirmation',
                    'message' => 'Policy submitted. Insurer will send asynchronous callback.',
                    'provider_reference' => $response['provider_reference'],
                    'policy' => $lockedPolicy->fresh(['stateHistories', 'issuanceAttempts']),
                ];
            }

            // SUCCESS:
            $lockedPolicy->provider_reference = $response['provider_reference'];
            $lockedPolicy->policy_number = $response['policy_number'];
            $lockedPolicy->last_error = null;
            $lockedPolicy->transitionTo(
                PolicyStatus::ISSUED,
                "Policy confirmed issued by insurer with number {$response['policy_number']}.",
                [
                    'provider_reference' => $response['provider_reference'],
                    'policy_number' => $response['policy_number'],
                ]
            );

            return [
                'status' => 'issued',
                'message' => 'Policy issued successfully.',
                'policy_number' => $lockedPolicy->policy_number,
                'policy' => $lockedPolicy->fresh(['stateHistories', 'issuanceAttempts']),
            ];
        });
    }

    /**
     * Process an incoming mock insurer webhook callback safely.
     * Enforces deduplication using event_id and protects ISSUED terminal state.
     */
    public function processCallback(array $payload): array
    {
        $eventId = $payload['event_id'] ?? null;
        $providerRef = $payload['provider_reference'] ?? null;
        $policyNumber = $payload['policy_number'] ?? null;
        $eventType = $payload['event_type'] ?? 'POLICY_ISSUED';

        if (! $eventId || ! $providerRef) {
            throw new InvalidArgumentException('Missing required callback parameters: event_id and provider_reference.');
        }

        return DB::transaction(function () use ($eventId, $providerRef, $policyNumber, $eventType, $payload) {
            // Check for duplicate webhook event (Reliability Case: Duplicate Callback)
            $existingEvent = ProviderEvent::where('event_id', $eventId)->first();
            if ($existingEvent) {
                return [
                    'status' => 'duplicate_ignored',
                    'message' => "Duplicate webhook event [{$eventId}] safely ignored.",
                    'event_id' => $eventId,
                    'provider_reference' => $providerRef,
                ];
            }

            // Record event intake
            $eventRecord = ProviderEvent::create([
                'event_id' => $eventId,
                'provider_reference' => $providerRef,
                'event_type' => $eventType,
                'policy_number' => $policyNumber,
                'payload' => $payload,
                'status' => 'PROCESSED',
                'processed_at' => now(),
            ]);

            // Locate corresponding policy
            $policy = PolicyRequest::where('provider_reference', $providerRef)->first();
            if (! $policy) {
                $eventRecord->update(['status' => 'ORPHANED_POLICY_NOT_FOUND']);

                return [
                    'status' => 'policy_not_found',
                    'message' => "No policy found matching provider reference [{$providerRef}].",
                ];
            }

            // Guard against overwriting already ISSUED policy
            if ($policy->status === PolicyStatus::ISSUED) {
                return [
                    'status' => 'already_issued',
                    'message' => 'Policy is already in confirmed ISSUED state. Webhook processed idempotently.',
                    'policy_number' => $policy->policy_number,
                ];
            }

            // Apply transition to ISSUED
            if ($policyNumber && empty($policy->policy_number)) {
                $policy->policy_number = $policyNumber;
            }
            $policy->transitionTo(
                PolicyStatus::ISSUED,
                "Confirmed via external insurer webhook callback [Event: {$eventId}]",
                ['event_id' => $eventId, 'provider_reference' => $providerRef, 'policy_number' => $policyNumber]
            );

            return [
                'status' => 'issued',
                'message' => 'Policy transitioned to ISSUED via provider callback.',
                'policy_number' => $policy->policy_number,
                'policy' => $policy->fresh(['stateHistories']),
            ];
        });
    }

    /**
     * Reconcile policy against external provider status.
     * Essential for recovering from TIMEOUT_AFTER_ISSUE or delayed callbacks.
     */
    public function reconcile(PolicyRequest $policy): array
    {
        if ($policy->status === PolicyStatus::ISSUED) {
            return [
                'status' => 'already_issued',
                'message' => 'Policy already confirmed ISSUED.',
                'policy_number' => $policy->policy_number,
            ];
        }

        // Try lookup by provider_reference first, then by (agent_id, client_request_id)
        $providerData = null;
        if (! empty($policy->provider_reference)) {
            $providerData = $this->mockInsurer->lookupPolicyByReference($policy->provider_reference);
        }

        if (! $providerData) {
            $providerData = $this->mockInsurer->lookupPolicyByClientRequest($policy->agent_id, $policy->client_request_id);
        }

        if (! $providerData) {
            return [
                'status' => 'unresolved',
                'message' => 'No matching policy found on provider ledger. Remains in current status.',
                'policy' => $policy,
            ];
        }

        if ($providerData['status'] === 'ISSUED') {
            $policy->provider_reference = $providerData['provider_reference'];
            $policy->policy_number = $providerData['policy_number'];
            $policy->last_error = null;
            $policy->transitionTo(
                PolicyStatus::ISSUED,
                'Reconciliation check confirmed policy exists and is ISSUED on external provider.',
                ['provider_data' => $providerData]
            );

            return [
                'status' => 'reconciled_issued',
                'message' => 'Reconciliation successful: Policy confirmed and updated to ISSUED.',
                'policy_number' => $policy->policy_number,
                'policy' => $policy->fresh(['stateHistories']),
            ];
        }

        if ($providerData['status'] === 'FAILED') {
            $policy->transitionTo(
                PolicyStatus::FAILED,
                'Reconciliation check confirmed policy request failed on provider.',
                ['provider_data' => $providerData]
            );

            return [
                'status' => 'reconciled_failed',
                'message' => 'Reconciliation confirmed provider failure.',
                'policy' => $policy->fresh(['stateHistories']),
            ];
        }

        return [
            'status' => 'still_pending',
            'message' => 'Provider reports policy is still in PENDING state.',
            'policy' => $policy,
        ];
    }
}
