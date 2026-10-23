<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class PolicyRequest extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'client_request_id',
        'agent_id',
        'customer_name',
        'date_of_birth',
        'destination',
        'trip_start',
        'trip_end',
        'travel_days',
        'plan_code',
        'sum_insured',
        'premium_amount',
        'premium_breakdown',
        'status',
        'policy_number',
        'provider_reference',
        'attempt_count',
        'last_error',
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'trip_start' => 'date:Y-m-d',
        'trip_end' => 'date:Y-m-d',
        'travel_days' => 'integer',
        'sum_insured' => 'integer',
        'premium_amount' => 'decimal:2',
        'premium_breakdown' => 'array',
        'status' => PolicyStatus::class,
        'attempt_count' => 'integer',
    ];

    public function stateHistories(): HasMany
    {
        return $this->hasMany(PolicyStateHistory::class)->orderBy('created_at', 'asc');
    }

    public function issuanceAttempts(): HasMany
    {
        return $this->hasMany(IssuanceAttempt::class)->orderBy('attempt_number', 'asc');
    }

    /**
     * Safely transition to a new status with validation against regression
     * and automatic audit trail recording.
     */
    public function transitionTo(PolicyStatus $targetStatus, ?string $reason = null, ?array $metadata = null): void
    {
        $current = $this->status;

        if ($current === $targetStatus) {
            return; // Idempotent no-op
        }

        if (! $current->canTransitionTo($targetStatus)) {
            throw new InvalidArgumentException(
                "Illegal state transition from {$current->value} to {$targetStatus->value} for policy request {$this->id}."
            );
        }

        $this->status = $targetStatus;
        $this->save();

        $this->stateHistories()->create([
            'from_status' => $current->value,
            'to_status' => $targetStatus->value,
            'reason' => $reason,
            'metadata' => $metadata,
            'created_at' => now(),
        ]);
    }
}
