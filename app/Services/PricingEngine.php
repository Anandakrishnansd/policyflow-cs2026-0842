<?php

namespace App\Services;

use Carbon\Carbon;
use InvalidArgumentException;

class PricingEngine
{
    public const BASE_RATES = [
        'BASIC' => 12.00,
        'PLUS' => 20.00,
        'PREMIUM' => 30.00,
    ];

    public const DESTINATION_MULTIPLIERS = [
        'DOMESTIC' => 1.00,
        'INTERNATIONAL' => 1.80,
    ];

    public const COVERAGE_MULTIPLIERS = [
        100000 => 1.00,
        300000 => 1.25,
        500000 => 1.50,
    ];

    /**
     * Calculate premium and return deterministic snapshot breakdown.
     *
     * @throws InvalidArgumentException
     */
    public function calculate(
        string $planCode,
        string $destination,
        int $sumInsured,
        string $dateOfBirth,
        string $tripStart,
        string $tripEnd
    ): array {
        $plan = strtoupper(trim($planCode));
        if (! isset(self::BASE_RATES[$plan])) {
            throw new InvalidArgumentException("Unsupported plan code: [{$planCode}]. Supported: BASIC, PLUS, PREMIUM.");
        }
        $baseDailyRate = self::BASE_RATES[$plan];

        $dest = strtoupper(trim($destination));
        if (! isset(self::DESTINATION_MULTIPLIERS[$dest])) {
            throw new InvalidArgumentException("Unsupported destination: [{$destination}]. Supported: DOMESTIC, INTERNATIONAL.");
        }
        $destinationMultiplier = self::DESTINATION_MULTIPLIERS[$dest];

        if (! isset(self::COVERAGE_MULTIPLIERS[$sumInsured])) {
            throw new InvalidArgumentException("Unsupported sum insured: [{$sumInsured}]. Supported: 100000, 300000, 500000.");
        }
        $coverageMultiplier = self::COVERAGE_MULTIPLIERS[$sumInsured];

        $startDate = Carbon::parse($tripStart)->startOfDay();
        $endDate = Carbon::parse($tripEnd)->startOfDay();

        if ($endDate->lt($startDate)) {
            throw new InvalidArgumentException("Trip end date [{$tripEnd}] cannot be before trip start date [{$tripStart}].");
        }

        // Days is inclusive: trip_end - trip_start + 1; minimum 1
        $travelDays = (int) $startDate->diffInDays($endDate) + 1;
        if ($travelDays < 1) {
            $travelDays = 1;
        }

        // Age calculated on trip start date
        $dob = Carbon::parse($dateOfBirth)->startOfDay();
        $ageOnTripStart = (int) $dob->diffInYears($startDate);
        if ($ageOnTripStart < 0) {
            throw new InvalidArgumentException("Date of birth [{$dateOfBirth}] cannot be in the future relative to trip start.");
        }

        $ageMultiplier = $this->getAgeMultiplier($ageOnTripStart);

        // Formula: premium = base_daily_rate × travel_days × age_multiplier × destination_multiplier × coverage_multiplier
        // Round final premium to 2 decimal places using decimal-safe arithmetic
        $unrounded = (float) $baseDailyRate * $travelDays * $ageMultiplier * $destinationMultiplier * $coverageMultiplier;
        $finalPremium = round($unrounded, 2);

        return [
            'premium_amount' => number_format($finalPremium, 2, '.', ''),
            'travel_days' => $travelDays,
            'age_on_trip_start' => $ageOnTripStart,
            'breakdown' => [
                'plan_code' => $plan,
                'base_daily_rate' => $baseDailyRate,
                'travel_days' => $travelDays,
                'age_on_trip_start' => $ageOnTripStart,
                'age_multiplier' => $ageMultiplier,
                'destination' => $dest,
                'destination_multiplier' => $destinationMultiplier,
                'sum_insured' => $sumInsured,
                'coverage_multiplier' => $coverageMultiplier,
                'unrounded_total' => $unrounded,
                'final_premium' => $finalPremium,
                'currency' => 'INR',
            ],
        ];
    }

    /**
     * Age multiplier brackets:
     * 0–17: 1.10
     * 18–60: 1.00
     * 61–70: 1.50
     * 71+: 2.00
     */
    public function getAgeMultiplier(int $age): float
    {
        if ($age <= 17) {
            return 1.10;
        }
        if ($age <= 60) {
            return 1.00;
        }
        if ($age <= 70) {
            return 1.50;
        }

        return 2.00;
    }
}
