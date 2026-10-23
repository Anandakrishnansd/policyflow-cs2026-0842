<?php

namespace Database\Seeders;

use App\Enums\PolicyStatus;
use App\Models\MockInsurerPolicy;
use App\Models\PolicyRequest;
use App\Services\PricingEngine;
use Illuminate\Database\Seeder;

class PolicyRequestSeeder extends Seeder
{
    public function run(): void
    {
        $pricing = app(PricingEngine::class);

        // 1. Spec Example 1: International Plus (Successfully Issued)
        $quote1 = $pricing->calculate('PLUS', 'INTERNATIONAL', 300000, '1998-04-10', '2026-11-10', '2026-11-14');
        $policy1 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Aditi Sharma',
            'date_of_birth' => '1998-04-10',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-10',
            'trip_end' => '2026-11-14',
            'travel_days' => $quote1['travel_days'],
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'premium_amount' => $quote1['premium_amount'],
            'premium_breakdown' => $quote1['breakdown'],
            'status' => PolicyStatus::ISSUED,
            'policy_number' => 'POL-2026-881920',
            'provider_reference' => 'PRV-SEED-01',
            'attempt_count' => 1,
        ]);
        $policy1->stateHistories()->createMany([
            ['from_status' => null, 'to_status' => 'VALIDATED', 'reason' => 'Intake validated successfully', 'created_at' => now()->subHours(2)],
            ['from_status' => 'VALIDATED', 'to_status' => 'SUBMITTED', 'reason' => 'Issuance attempt #1 dispatched to mock insurer', 'created_at' => now()->subHours(2)->addMinutes(1)],
            ['from_status' => 'SUBMITTED', 'to_status' => 'ISSUED', 'reason' => 'Policy confirmed issued by insurer with number POL-2026-881920', 'created_at' => now()->subHours(2)->addMinutes(2)],
        ]);
        $policy1->issuanceAttempts()->create([
            'attempt_number' => 1,
            'scenario' => 'SUCCESS',
            'outcome' => 'SUCCESS',
            'http_status' => 200,
            'response_payload' => ['success' => true, 'policy_number' => 'POL-2026-881920', 'provider_reference' => 'PRV-SEED-01'],
        ]);
        MockInsurerPolicy::create([
            'provider_reference' => 'PRV-SEED-01',
            'client_request_id' => 'AGT-2026-0001',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Aditi Sharma',
            'status' => 'ISSUED',
            'policy_number' => 'POL-2026-881920',
            'scenario' => 'SUCCESS',
            'premium_amount' => $quote1['premium_amount'],
        ]);

        // 2. Domestic Senior Basic (Validated, ready to submit)
        $quote2 = $pricing->calculate('BASIC', 'DOMESTIC', 100000, '1959-02-15', '2026-12-01', '2026-12-10');
        $policy2 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0002',
            'agent_id' => 'AGENT-09',
            'customer_name' => 'Rajesh Verma',
            'date_of_birth' => '1959-02-15',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-12-01',
            'trip_end' => '2026-12-10',
            'travel_days' => $quote2['travel_days'],
            'plan_code' => 'BASIC',
            'sum_insured' => 100000,
            'premium_amount' => $quote2['premium_amount'],
            'premium_breakdown' => $quote2['breakdown'],
            'status' => PolicyStatus::VALIDATED,
            'attempt_count' => 0,
        ]);
        $policy2->stateHistories()->create([
            'from_status' => null,
            'to_status' => 'VALIDATED',
            'reason' => 'Intake validated successfully and premium calculated.',
            'created_at' => now()->subHours(1),
        ]);

        // 3. Timed-out policy (NEEDS_RECONCILIATION)
        $quote3 = $pricing->calculate('PREMIUM', 'INTERNATIONAL', 500000, '1992-07-22', '2026-11-20', '2026-11-28');
        $policy3 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Vikram Malhotra',
            'date_of_birth' => '1992-07-22',
            'destination' => 'INTERNATIONAL',
            'trip_start' => '2026-11-20',
            'trip_end' => '2026-11-28',
            'travel_days' => $quote3['travel_days'],
            'plan_code' => 'PREMIUM',
            'sum_insured' => 500000,
            'premium_amount' => $quote3['premium_amount'],
            'premium_breakdown' => $quote3['breakdown'],
            'status' => PolicyStatus::NEEDS_RECONCILIATION,
            'provider_reference' => 'PRV-TO-SEED03',
            'attempt_count' => 1,
            'last_error' => 'Connection timed out: Read timeout while waiting for insurer response packet.',
        ]);
        $policy3->stateHistories()->createMany([
            ['from_status' => null, 'to_status' => 'VALIDATED', 'reason' => 'Intake validated successfully', 'created_at' => now()->subMinutes(45)],
            ['from_status' => 'VALIDATED', 'to_status' => 'SUBMITTED', 'reason' => 'Issuance attempt #1 dispatched to mock insurer (Scenario: TIMEOUT_AFTER_ISSUE)', 'created_at' => now()->subMinutes(44)],
            ['from_status' => 'SUBMITTED', 'to_status' => 'NEEDS_RECONCILIATION', 'reason' => 'Network timeout encountered. State flagged for provider reconciliation to prevent duplicate issuance.', 'created_at' => now()->subMinutes(43)],
        ]);
        MockInsurerPolicy::create([
            'provider_reference' => 'PRV-TO-SEED03',
            'client_request_id' => 'AGT-2026-0003',
            'agent_id' => 'AGENT-17',
            'customer_name' => 'Vikram Malhotra',
            'status' => 'ISSUED',
            'policy_number' => 'POL-2026-773412',
            'scenario' => 'TIMEOUT_AFTER_ISSUE',
            'premium_amount' => $quote3['premium_amount'],
        ]);

        // 4. Temporary failure (FAILED - eligible for retry)
        $quote4 = $pricing->calculate('PLUS', 'DOMESTIC', 300000, '2001-10-05', '2026-11-01', '2026-11-05');
        $policy4 = PolicyRequest::create([
            'client_request_id' => 'AGT-2026-0004',
            'agent_id' => 'AGENT-24',
            'customer_name' => 'Pooja Iyer',
            'date_of_birth' => '2001-10-05',
            'destination' => 'DOMESTIC',
            'trip_start' => '2026-11-01',
            'trip_end' => '2026-11-05',
            'travel_days' => $quote4['travel_days'],
            'plan_code' => 'PLUS',
            'sum_insured' => 300000,
            'premium_amount' => $quote4['premium_amount'],
            'premium_breakdown' => $quote4['breakdown'],
            'status' => PolicyStatus::FAILED,
            'attempt_count' => 1,
            'last_error' => 'Mock Insurer Error 503: Underwriting service temporarily unavailable.',
        ]);
        $policy4->stateHistories()->createMany([
            ['from_status' => null, 'to_status' => 'VALIDATED', 'reason' => 'Intake validated successfully', 'created_at' => now()->subMinutes(30)],
            ['from_status' => 'VALIDATED', 'to_status' => 'SUBMITTED', 'reason' => 'Issuance attempt #1 dispatched to mock insurer (Scenario: TEMPORARY_FAILURE)', 'created_at' => now()->subMinutes(29)],
            ['from_status' => 'SUBMITTED', 'to_status' => 'FAILED', 'reason' => 'Insurer returned temporary error. Request eligible for safe retry.', 'created_at' => now()->subMinutes(28)],
        ]);
    }
}
