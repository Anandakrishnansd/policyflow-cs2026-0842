<?php

namespace Tests\Feature;

use App\Models\PolicyRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyIntakeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test valid intake produces HTTP 201 Created and persisted snapshot.
     */
    public function test_valid_intake_creates_policy_with_201(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'created',
                'is_duplicate' => false,
                'data' => [
                    'client_request_id' => 'AGT-2026-0001',
                    'agent_id' => 'AGENT-17',
                    'premium_amount' => '225.00',
                    'status' => 'VALIDATED',
                ],
            ]);

        $this->assertDatabaseHas('policy_requests', [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'premium_amount' => 225.00,
            'status' => 'VALIDATED',
        ]);
    }

    /**
     * Test repeated submission with same agent_id + client_request_id
     * returns HTTP 200 OK and does NOT create another logical request.
     */
    public function test_repeated_intake_returns_200_without_duplicate(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Sample Customer',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        // 1st request -> 201 Created
        $first = $this->postJson('/api/policy-requests', $payload);
        $first->assertStatus(201);
        $firstId = $first->json('data.id');

        // 2nd request -> 200 OK (Idempotent safe duplicate return)
        $second = $this->postJson('/api/policy-requests', $payload);
        $second->assertStatus(200)
            ->assertJson([
                'status' => 'duplicate_request_returned',
                'is_duplicate' => true,
                'data' => [
                    'id' => $firstId,
                    'client_request_id' => 'AGT-2026-0001',
                    'agent_id' => 'AGENT-17',
                ],
            ]);

        // Verify only 1 record exists in the database
        $this->assertEquals(1, PolicyRequest::where('client_request_id', 'AGT-2026-0001')->count());
    }

    /**
     * Validation failures return HTTP 422 with meaningful error messages.
     */
    public function test_invalid_dates_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-9999',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Invalid Dates User',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-20',
            'trip_end' => '2026-11-10', // End before start!
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['trip_end']]);
    }

    public function test_unsupported_plan_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-9998',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Invalid Plan User',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'SUPER_PLATINUM', // Unsupported!
            'sum_insured' => 300000,
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['plan_code']]);
    }

    public function test_unsupported_sum_insured_rejected_with_422(): void
    {
        $payload = [
            'client_request_id' => 'AGT-2026-9997',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Invalid Sum User',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'plan_code' => 'BASIC',
            'sum_insured' => 250000, // Unsupported!
        ];

        $response = $this->postJson('/api/policy-requests', $payload);
        $response->assertStatus(422)
            ->assertJsonStructure(['errors' => ['sum_insured']]);
    }
}
