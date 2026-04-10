<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Unit;

use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\PlanPricing;
use OpenSaas\SubscriptionsForLaravel\Tests\TestCase;

class PlanPricingTest extends TestCase
{
    public function test_it_does_not_have_periodicity_when_type_is_null(): void
    {
        $pricing = new PlanPricing();
        $pricing->periodicity_type = null;
        $pricing->periodicity_value = 1;

        $this->assertTrue($pricing->doesNotHavePeriodicity());
    }

    public function test_it_does_not_have_periodicity_when_value_is_null(): void
    {
        $pricing = new PlanPricing();
        $pricing->periodicity_type = PeriodicityType::Monthly;
        $pricing->periodicity_value = null;

        $this->assertTrue($pricing->doesNotHavePeriodicity());
    }

    public function test_it_has_periodicity_when_both_are_set(): void
    {
        $pricing = new PlanPricing();
        $pricing->periodicity_type = PeriodicityType::Monthly;
        $pricing->periodicity_value = 1;

        $this->assertFalse($pricing->doesNotHavePeriodicity());
    }

    public function test_it_does_not_have_trial_when_trial_days_is_null(): void
    {
        $pricing = new PlanPricing();
        $pricing->trial_days = null;

        $this->assertTrue($pricing->doesNotHaveTrial());
    }

    public function test_it_does_not_have_trial_when_trial_days_is_zero(): void
    {
        $pricing = new PlanPricing();
        $pricing->trial_days = 0;

        $this->assertTrue($pricing->doesNotHaveTrial());
    }

    public function test_it_has_trial_when_trial_days_is_set(): void
    {
        $pricing = new PlanPricing();
        $pricing->trial_days = 14;

        $this->assertFalse($pricing->doesNotHaveTrial());
    }

    public function test_it_does_not_have_grace_period_when_grace_days_is_null(): void
    {
        $pricing = new PlanPricing();
        $pricing->grace_days = null;

        $this->assertTrue($pricing->doesNotHaveGracePeriod());
    }

    public function test_it_does_not_have_grace_period_when_grace_days_is_zero(): void
    {
        $pricing = new PlanPricing();
        $pricing->grace_days = 0;

        $this->assertTrue($pricing->doesNotHaveGracePeriod());
    }

    public function test_it_has_grace_period_when_grace_days_is_set(): void
    {
        $pricing = new PlanPricing();
        $pricing->grace_days = 5;

        $this->assertFalse($pricing->doesNotHaveGracePeriod());
    }
}
