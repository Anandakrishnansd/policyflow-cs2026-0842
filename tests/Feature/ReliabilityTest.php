<?php

namespace Tests\Feature;

use App\Enums\PolicyStatus;
use App\Models\PolicyRequest;
use App\Models\ProviderEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReliabilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * RELIABILITY CASE 1: Duplicate Intake
     * Submitting the same client_request_id + agent_id repeatedly returns 200 OK
     * and preserves the single logical database entity without duplicate rows.
     */
    public function test_case_1_duplicate_intake_is_idempotent(): void
    {
        $payload = [
            'client_request_id' => 'AGT-CONCURRENT-001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Concurrent Customer',
            'date_of_birth' => '1995-05-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ];

        // 1st submission -> 201 Created
        $res1 = $this->postJson('/api/policy-requests', $payload);
        $res1->assertStatus(201);
        $policyId = $res1->json('data.id');

        // 2nd repeated submission -> 200 OK
        $res2 = $this->postJson('/api/policy-requests', $payload);
        $res2->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate_request_returned',
                'is_duplicate' => true,
                'data' => ['id' => $policyId],
            ]);

        // 3rd repeated submission -> 200 OK
        $res3 = $this->postJson('/api/policy-requests', $payload);
        $res3->assertStatus(200);

        // Database integrity check: exactly 1 row exists
        $this->assertEquals(1, PolicyRequest::where('client_request_id', 'AGT-CONCURRENT-001')->count());
    }

    /**
     * RELIABILITY CASE 2: Timeout After Success
     * Insurer issues policy internally but client call encounters a read timeout.
     * System marks policy as NEEDS_RECONCILIATION.
     * Reconcile operation verifies provider status, retrieves policy number, and marks ISSUED.
     * Prevents blind duplicate issuance.
     */
    public function test_case_2_timeout_after_success_and_reconciliation(): void
    {
        // 1. Create intake
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-TIMEOUT-001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Timeout Customer',
            'date_of_birth' => '1990-01-01',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $policyId = $intake->json('data.id');

        // 2. Submit with TIMEOUT_AFTER_ISSUE scenario
        $submit = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
        ]);

        // Returns HTTP 504 Gateway Timeout simulation
        $submit->assertStatus(504);

        // Verify policy is NOT marked failed or blindly reissued; it is in NEEDS_RECONCILIATION
        $policy = PolicyRequest::find($policyId);
        $this->assertEquals(PolicyStatus::NEEDS_RECONCILIATION, $policy->status);
        $this->assertNotNull($policy->provider_reference);
        $this->assertNull($policy->policy_number); // Policy number was not received due to timeout

        // 3. Reconcile against provider
        $reconcile = $this->postJson("/api/policy-requests/{$policyId}/reconcile");
        $reconcile->assertStatus(200)
            ->assertJson([
                'status' => 'reconciled_issued',
            ]);

        // Verify policy is now confirmed ISSUED with the provider's policy number
        $policy->refresh();
        $this->assertEquals(PolicyStatus::ISSUED, $policy->status);
        $this->assertNotNull($policy->policy_number);
        $this->assertStringStartsWith('POL-2026-', $policy->policy_number);
    }

    /**
     * RELIABILITY CASE 3: Duplicate Webhook Callback
     * When external provider delivers identical webhook events repeatedly,
     * the system idempotently suppresses duplicates and prevents repeated state transitions.
     */
    public function test_case_3_duplicate_callback_deduplication(): void
    {
        // 1. Create intake and submit under DELAYED_CALLBACK scenario
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-ASYNC-001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Async Customer',
            'date_of_birth' => '1992-03-14',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
        ]);
        $policyId = $intake->json('data.id');

        $submit = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'DELAYED_CALLBACK',
        ]);
        $submit->assertStatus(202);
        $providerRef = $submit->json('provider_reference');

        // 2. First callback arrives
        $callbackPayload = [
            'event_id' => 'EVT-WEBHOOK-999',
            'provider_reference' => $providerRef,
            'event_type' => 'POLICY_ISSUED',
            'policy_number' => 'POL-2026-999111',
        ];

        $callback1 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $callback1->assertStatus(200)
            ->assertJson([
                'status' => 'issued',
                'policy_number' => 'POL-2026-999111',
            ]);

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals(PolicyStatus::ISSUED, $policy->status);
        $this->assertEquals('POL-2026-999111', $policy->policy_number);

        // 3. Duplicate callback arrives with the exact same event_id
        $callback2 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $callback2->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate_ignored',
            ]);

        // 4. Third identical callback arrives
        $callback3 = $this->postJson('/api/mock-insurer/callback', $callbackPayload);
        $callback3->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate_ignored',
            ]);

        // Verify only one event was registered in provider_events
        $this->assertEquals(1, ProviderEvent::where('event_id', 'EVT-WEBHOOK-999')->count());
    }

    /**
     * RELIABILITY CASE 4: Retry After Temporary Failure
     * Retries after 503 error do not create duplicate records, do not corrupt the original
     * accepted premium snapshot, and successfully promote policy to ISSUED.
     */
    public function test_case_4_retry_after_temporary_failure(): void
    {
        // 1. Create intake
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-RETRY-001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Retry Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $policyId = $intake->json('data.id');
        $originalPremium = $intake->json('data.premium_amount');

        // 2. First submission simulates TEMPORARY_FAILURE (503)
        $submit1 = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'TEMPORARY_FAILURE',
        ]);
        $submit1->assertStatus(502);

        $policy = PolicyRequest::find($policyId);
        $this->assertEquals(PolicyStatus::FAILED, $policy->status);
        $this->assertEquals(1, $policy->attempt_count);
        $this->assertNull($policy->policy_number);

        // 3. Retry submission under SUCCESS scenario
        $submit2 = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'SUCCESS',
        ]);
        $submit2->assertStatus(200)
            ->assertJson([
                'status' => 'issued',
            ]);

        $policy->refresh();
        $this->assertEquals(PolicyStatus::ISSUED, $policy->status);
        $this->assertEquals(2, $policy->attempt_count);
        $this->assertNotNull($policy->policy_number);

        // Crucial requirement: Original accepted premium snapshot was preserved intact!
        $this->assertEquals($originalPremium, (string) $policy->premium_amount);
    }

    /**
     * Confirmed ISSUED state cannot be overwritten or regressed by subsequent events.
     */
    public function test_confirmed_issued_state_cannot_be_overwritten(): void
    {
        // Create and issue policy
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-TERMINAL-001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Terminal Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $policyId = $intake->json('data.id');

        $submit = $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);
        $originalPolicyNum = $submit->json('policy_number');

        // Attempting to inject a webhook with a different policy number on an already ISSUED policy
        $callback = $this->postJson('/api/mock-insurer/callback', [
            'event_id' => 'EVT-ROGUE-999',
            'provider_reference' => PolicyRequest::find($policyId)->provider_reference,
            'event_type' => 'POLICY_REJECTED',
            'policy_number' => 'POL-ROGUE-000000',
        ]);

        $callback->assertStatus(200)
            ->assertJson(['status' => 'already_issued']);

        // Verify policy remains ISSUED and unmodified
        $policy = PolicyRequest::find($policyId);
        $this->assertEquals(PolicyStatus::ISSUED, $policy->status);
        $this->assertEquals($originalPolicyNum, $policy->policy_number);
    }
}
