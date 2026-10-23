<?php

namespace Tests\Unit;

use App\Services\PricingEngine;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PricingEngineTest extends TestCase
{
    protected PricingEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new PricingEngine();
    }

    /**
     * Test the exact calculation example given in Section 4 of the specification:
     * - Plan: PLUS (base ₹20)
     * - Destination: INTERNATIONAL (multiplier 1.80)
     * - Coverage: ₹300,000 (multiplier 1.25)
     * - DOB: 1998-04-10, Trip: 2026-11-10 to 2026-11-14 (5 days)
     * - Age on trip start: 28 years (multiplier 1.00)
     * Expected: 20 × 5 × 1.00 × 1.80 × 1.25 = ₹225.00
     */
    public function test_exact_spec_pricing_example(): void
    {
        $result = $this->engine->calculate(
            planCode: 'PLUS',
            destination: 'INTERNATIONAL',
            sumInsured: 300000,
            dateOfBirth: '1998-04-10',
            tripStart: '2026-11-10',
            tripEnd: '2026-11-14'
        );

        $this->assertEquals('225.00', $result['premium_amount']);
        $this->assertEquals(5, $result['travel_days']);
        $this->assertEquals(28, $result['age_on_trip_start']);
        $this->assertEquals(1.00, $result['breakdown']['age_multiplier']);
        $this->assertEquals(1.80, $result['breakdown']['destination_multiplier']);
        $this->assertEquals(1.25, $result['breakdown']['coverage_multiplier']);
    }

    /**
     * Test age multiplier bracket boundaries:
     * 0-17: 1.10
     * 18-60: 1.00
     * 61-70: 1.50
     * 71+: 2.00
     */
    public function test_age_multiplier_brackets(): void
    {
        $this->assertEquals(1.10, $this->engine->getAgeMultiplier(0));
        $this->assertEquals(1.10, $this->engine->getAgeMultiplier(17));

        $this->assertEquals(1.00, $this->engine->getAgeMultiplier(18));
        $this->assertEquals(1.00, $this->engine->getAgeMultiplier(40));
        $this->assertEquals(1.00, $this->engine->getAgeMultiplier(60));

        $this->assertEquals(1.50, $this->engine->getAgeMultiplier(61));
        $this->assertEquals(1.50, $this->engine->getAgeMultiplier(70));

        $this->assertEquals(2.00, $this->engine->getAgeMultiplier(71));
        $this->assertEquals(2.00, $this->engine->getAgeMultiplier(85));
    }

    /**
     * Inclusive travel days calculation:
     * Same day trip start & end must count as 1 inclusive day.
     */
    public function test_inclusive_single_day_calculation(): void
    {
        $result = $this->engine->calculate(
            planCode: 'BASIC',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '2000-01-01',
            tripStart: '2026-12-01',
            tripEnd: '2026-12-01'
        );

        // BASIC base 12 * 1 day * 1.00 age * 1.00 dest * 1.00 cov = 12.00
        $this->assertEquals(1, $result['travel_days']);
        $this->assertEquals('12.00', $result['premium_amount']);
    }

    /**
     * Rejects trip_end earlier than trip_start.
     */
    public function test_rejects_inverted_dates(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->engine->calculate(
            planCode: 'PLUS',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '2000-01-01',
            tripStart: '2026-12-10',
            tripEnd: '2026-12-05'
        );
    }

    /**
     * Rejects unsupported plan code.
     */
    public function test_rejects_unsupported_plan(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->engine->calculate(
            planCode: 'DIAMOND_VIP',
            destination: 'DOMESTIC',
            sumInsured: 100000,
            dateOfBirth: '2000-01-01',
            tripStart: '2026-12-01',
            tripEnd: '2026-12-05'
        );
    }

    /**
     * Rejects unsupported coverage sum.
     */
    public function test_rejects_unsupported_coverage(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->engine->calculate(
            planCode: 'PLUS',
            destination: 'DOMESTIC',
            sumInsured: 999999,
            dateOfBirth: '2000-01-01',
            tripStart: '2026-12-01',
            tripEnd: '2026-12-05'
        );
    }
}
