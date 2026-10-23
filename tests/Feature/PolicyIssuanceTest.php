<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIssuanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Successful provider submission transitions to ISSUED and assigns a unique policy number.
     */
    public function test_successful_provider_submission_issues_policy(): void
    {
        // 1. Create a policy request
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $intake->assertStatus(201);
        $policyId = $intake->json('data.id');

        // 2. Submit to mock insurer under SUCCESS scenario
        $submit = $this->postJson("/api/policy-requests/{$policyId}/submit", [
            'scenario' => 'SUCCESS',
        ]);

        $submit->assertStatus(200)
            ->assertJson([
                'status' => 'issued',
                'message' => 'Policy issued successfully.',
            ]);

        $policyNumber = $submit->json('policy_number');
        $this->assertNotEmpty($policyNumber);
        $this->assertStringStartsWith('POL-2026-', $policyNumber);

        // 3. Verify database state
        $policy = PolicyRequest::with('stateHistories')->find($policyId);
        $this->assertEquals('ISSUED', $policy->status->value);
        $this->assertEquals($policyNumber, $policy->policy_number);
        $this->assertNotNull($policy->provider_reference);

        // 4. Check audit trail
        $statuses = $policy->stateHistories->pluck('to_status')->toArray();
        $this->assertContains('VALIDATED', $statuses);
        $this->assertContains('SUBMITTED', $statuses);
        $this->assertContains('ISSUED', $statuses);
    }

    /**
     * Submitting an already ISSUED policy must be safe and idempotent:
     * Does not contact provider again or alter the policy number.
     */
    public function test_submitting_already_issued_policy_is_idempotent(): void
    {
        // Create and issue
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $policyId = $intake->json('data.id');

        $firstSubmit = $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);
        $originalPolicyNum = $firstSubmit->json('policy_number');

        // Second submission attempt
        $secondSubmit = $this->postJson("/api/policy-requests/{$policyId}/submit", ['scenario' => 'SUCCESS']);
        $secondSubmit->assertStatus(200)
            ->assertJson([
                'status' => 'already_issued',
                'policy_number' => $originalPolicyNum,
            ]);

        // Verify policy number remains unchanged and attempts count didn't increment
        $policy = PolicyRequest::find($policyId);
        $this->assertEquals($originalPolicyNum, $policy->policy_number);
        $this->assertEquals(1, $policy->attempt_count);
    }

    /**
     * GET /api/policy-requests/{id} returns state and audit trail.
     */
    public function test_get_policy_details_includes_audit_trail(): void
    {
        $intake = $this->postJson('/api/policy-requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ]);
        $policyId = $intake->json('data.id');

        $response = $this->getJson("/api/policy-requests/{$policyId}");
        $response->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'id',
                    'client_request_id',
                    'premium_amount',
                    'premium_breakdown',
                    'status',
                    'state_histories',
                ],
            ]);
    }
}
