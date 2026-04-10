<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Feature;
use OpenSaas\SubscriptionsForLaravel\Plan;
use OpenSaas\SubscriptionsForLaravel\PlanPricing;
use OpenSaas\SubscriptionsForLaravel\Tests\TestCase;

abstract class FeatureTestCase extends TestCase
{
    use RefreshDatabase;

    protected function createUser(array $attributes = []): User
    {
        return User::forceCreate(array_merge([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
        ], $attributes));
    }

    protected function createPlan(array $attributes = []): Plan
    {
        return Plan::forceCreate(array_merge([
            'slug' => 'pro',
        ], $attributes));
    }

    protected function createFeature(array $attributes = []): Feature
    {
        return Feature::forceCreate(array_merge([
            'slug' => 'api-calls',
            'type' => FeatureType::Consumable,
        ], $attributes));
    }

    protected function attachFeatureToPlan(
        Plan $plan,
        Feature $feature,
        float $charges = 100,
        ?PeriodicityType $periodicityType = PeriodicityType::Monthly,
        ?int $periodicityValue = 1,
    ): void {
        DB::table('feature_plan')->insert([
            'plan_id' => $plan->id,
            'feature_id' => $feature->id,
            'charges' => $charges,
            'periodicity_type' => $periodicityType?->value,
            'periodicity_value' => $periodicityValue,
        ]);
    }

    protected function createPlanPricing(Plan $plan, array $attributes = []): PlanPricing
    {
        return PlanPricing::forceCreate(array_merge([
            'plan_id' => $plan->id,
            'slug' => 'monthly',
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'trial_days' => null,
            'grace_days' => null,
        ], $attributes));
    }
}
