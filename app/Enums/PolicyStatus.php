<?php

namespace App\Enums;

enum PolicyStatus: string
{
    case RECEIVED = 'RECEIVED';
    case VALIDATED = 'VALIDATED';
    case SUBMITTED = 'SUBMITTED';
    case PENDING_CONFIRMATION = 'PENDING_CONFIRMATION';
    case ISSUED = 'ISSUED';
    case FAILED = 'FAILED';
    case NEEDS_RECONCILIATION = 'NEEDS_RECONCILIATION';

    /**
     * Determine if a transition from current state to the target state is permissible.
     */
    public function canTransitionTo(PolicyStatus $target): bool
    {
        // Terminal state: once ISSUED, never regress or change
        if ($this === self::ISSUED) {
            return false;
        }

        return match ($this) {
            self::RECEIVED => in_array($target, [self::VALIDATED, self::FAILED], true),
            self::VALIDATED => in_array($target, [self::SUBMITTED, self::FAILED], true),
            self::SUBMITTED => in_array($target, [
                self::ISSUED,
                self::PENDING_CONFIRMATION,
                self::NEEDS_RECONCILIATION,
                self::FAILED,
            ], true),
            self::PENDING_CONFIRMATION => in_array($target, [
                self::ISSUED,
                self::FAILED,
                self::NEEDS_RECONCILIATION,
            ], true),
            self::NEEDS_RECONCILIATION => in_array($target, [
                self::ISSUED,
                self::FAILED,
                self::SUBMITTED, // If retrying issuance under explicit reconciliation
            ], true),
            self::FAILED => in_array($target, [
                self::SUBMITTED, // Safe retry after temporary failure
                self::NEEDS_RECONCILIATION,
            ], true),
            self::ISSUED => false,
        };
    }
}
