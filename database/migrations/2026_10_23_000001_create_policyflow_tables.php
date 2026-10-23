<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Policy Requests Intake and State Tracking
        Schema::create('policy_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('client_request_id', 64);
            $table->string('agent_id', 64);
            $table->string('customer_name', 120);
            $table->date('date_of_birth');
            $table->string('destination', 32); // DOMESTIC, INTERNATIONAL
            $table->date('trip_start');
            $table->date('trip_end');
            $table->unsignedInteger('travel_days');
            $table->string('plan_code', 32); // BASIC, PLUS, PREMIUM
            $table->unsignedInteger('sum_insured'); // 100000, 300000, 500000
            $table->decimal('premium_amount', 10, 2);
            $table->json('premium_breakdown');
            $table->string('status', 32)->default('RECEIVED');
            $table->string('policy_number', 64)->nullable()->unique();
            $table->string('provider_reference', 64)->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Enforce database-level uniqueness across agent_id + client_request_id for idempotency
            $table->unique(['agent_id', 'client_request_id'], 'uq_policy_agent_client_req');
            $table->index(['status', 'created_at']);
        });

        // 2. Issuance Attempts Log
        Schema::create('issuance_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->unsignedInteger('attempt_number');
            $table->string('scenario', 64)->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->integer('http_status')->nullable();
            $table->string('outcome', 32); // SUCCESS, PENDING, FAILED, TIMED_OUT
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['policy_request_id', 'attempt_number']);
        });

        // 3. Provider Webhook Events (Deduplication store)
        Schema::create('provider_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 64)->unique(); // Webhook idempotency key
            $table->string('provider_reference', 64)->index();
            $table->string('event_type', 48); // POLICY_ISSUED, POLICY_REJECTED, etc.
            $table->string('policy_number', 64)->nullable();
            $table->json('payload');
            $table->string('status', 32)->default('PROCESSED'); // PROCESSED, IGNORED_DUPLICATE, FAILED
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // 4. Audit Trail for All State Transitions
        Schema::create('policy_state_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('policy_request_id')->constrained('policy_requests')->cascadeOnDelete();
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32);
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['policy_request_id', 'created_at']);
        });

        // 5. Mock Insurer Internal Records (Simulates external insurer ledger for status lookups / timeouts)
        Schema::create('mock_insurer_policies', function (Blueprint $table) {
            $table->id();
            $table->string('provider_reference', 64)->unique();
            $table->string('client_request_id', 64);
            $table->string('agent_id', 64);
            $table->string('customer_name', 120);
            $table->string('status', 32); // ISSUED, PENDING, FAILED
            $table->string('policy_number', 64)->nullable()->unique();
            $table->string('scenario', 64);
            $table->decimal('premium_amount', 10, 2);
            $table->timestamps();

            $table->index(['agent_id', 'client_request_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mock_insurer_policies');
        Schema::dropIfExists('policy_state_histories');
        Schema::dropIfExists('provider_events');
        Schema::dropIfExists('issuance_attempts');
        Schema::dropIfExists('policy_requests');
    }
};
