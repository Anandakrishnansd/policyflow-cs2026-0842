<?php

namespace App\Services;

use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use Illuminate\Support\Str;

class MockInsurerService
{
    /**
     * Submit policy issuance to the mock insurer.
     * Simulates various production insurer quirks based on scenario flag.
     */
    public function issuePolicy(PolicyRequest $policy, string $scenario = 'SUCCESS'): array
    {
        $scenario = strtoupper(trim($scenario));

        switch ($scenario) {
            case 'TEMPORARY_FAILURE':
                return [
                    'http_status' => 503,
                    'success' => false,
                    'error' => 'Mock Insurer Error 503: Underwriting service temporarily unavailable.',
                    'retriable' => true,
                    'provider_reference' => null,
                    'policy_number' => null,
                ];

            case 'TIMEOUT_AFTER_ISSUE':
                // The insurer actually generated and issued the policy internally,
                // but the response packet was dropped or timed out before reaching the client!
                $providerRef = 'PRV-TO-' . strtoupper(Str::random(8));
                $policyNumber = 'POL-2026-' . mt_rand(100000, 999999);

                MockInsurerPolicy::create([
                    'provider_reference' => $providerRef,
                    'client_request_id' => $policy->client_request_id,
                    'agent_id' => $policy->agent_id,
                    'customer_name' => $policy->customer_name,
                    'status' => 'ISSUED',
                    'policy_number' => $policyNumber,
                    'scenario' => $scenario,
                    'premium_amount' => $policy->premium_amount,
                ]);

                return [
                    'http_status' => 408,
                    'success' => false,
                    'timed_out' => true,
                    'error' => 'Connection timed out: Read timeout while waiting for insurer response packet.',
                    'provider_reference' => $providerRef,
                    'policy_number' => null, // Client did not receive the policy number due to timeout
                ];

            case 'DELAYED_CALLBACK':
                // Provider accepts the request, marks pending, and will notify via async webhook callback
                $providerRef = 'PRV-ASYNC-' . strtoupper(Str::random(8));
                $policyNumber = 'POL-2026-' . mt_rand(100000, 999999);

                MockInsurerPolicy::create([
                    'provider_reference' => $providerRef,
                    'client_request_id' => $policy->client_request_id,
                    'agent_id' => $policy->agent_id,
                    'customer_name' => $policy->customer_name,
                    'status' => 'ISSUED', // Ready on insurer side, waiting for callback dispatch
                    'policy_number' => $policyNumber,
                    'scenario' => $scenario,
                    'premium_amount' => $policy->premium_amount,
                ]);

                return [
                    'http_status' => 202,
                    'success' => true,
                    'provider_reference' => $providerRef,
                    'policy_number' => null,
                    'state' => 'PENDING_CONFIRMATION',
                    'message' => 'Policy issuance queued by external insurer; awaiting confirmation webhook callback.',
                ];

            case 'SUCCESS':
            default:
                // Standard happy path
                $providerRef = 'PRV-' . strtoupper(Str::random(8));
                $policyNumber = 'POL-2026-' . mt_rand(100000, 999999);

                MockInsurerPolicy::create([
                    'provider_reference' => $providerRef,
                    'client_request_id' => $policy->client_request_id,
                    'agent_id' => $policy->agent_id,
                    'customer_name' => $policy->customer_name,
                    'status' => 'ISSUED',
                    'policy_number' => $policyNumber,
                    'scenario' => 'SUCCESS',
                    'premium_amount' => $policy->premium_amount,
                ]);

                return [
                    'http_status' => 200,
                    'success' => true,
                    'provider_reference' => $providerRef,
                    'policy_number' => $policyNumber,
                    'state' => 'ISSUED',
                ];
        }
    }

    /**
     * Provider status lookup endpoint: Allows PolicyFlow engine to safely reconcile
     * unconfirmed or timed-out policies before issuing duplicate requests.
     */
    public function lookupPolicyByReference(string $providerReference): ?array
    {
        $record = MockInsurerPolicy::where('provider_reference', $providerReference)->first();
        if (! $record) {
            return null;
        }

        return [
            'provider_reference' => $record->provider_reference,
            'status' => $record->status,
            'policy_number' => $record->policy_number,
            'client_request_id' => $record->client_request_id,
            'agent_id' => $record->agent_id,
            'premium_amount' => $record->premium_amount,
        ];
    }

    /**
     * Look up by client_request_id and agent_id (e.g. if provider reference was lost during network drop)
     */
    public function lookupPolicyByClientRequest(string $agentId, string $clientRequestId): ?array
    {
        $record = MockInsurerPolicy::where('agent_id', $agentId)
            ->where('client_request_id', $clientRequestId)
            ->first();

        if (! $record) {
            return null;
        }

        return [
            'provider_reference' => $record->provider_reference,
            'status' => $record->status,
            'policy_number' => $record->policy_number,
            'client_request_id' => $record->client_request_id,
            'agent_id' => $record->agent_id,
            'premium_amount' => $record->premium_amount,
        ];
    }
}
