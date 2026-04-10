<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Feature;

use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;

class RelationshipTest extends FeatureTestCase
{
    public function test_feature_belongs_to_many_plans(): void
    {
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $planA = $this->createPlan(['slug' => 'basic']);
        $planB = $this->createPlan(['slug' => 'pro']);

        $this->attachFeatureToPlan($planA, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);
        $this->attachFeatureToPlan($planB, $feature, charges: 500, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $plans = $feature->plans;

        $this->assertCount(2, $plans);
        $this->assertTrue($plans->contains('id', $planA->id));
        $this->assertTrue($plans->contains('id', $planB->id));
    }

    public function test_feature_plan_pivot_has_charges_and_periodicity(): void
    {
        $plan = $this->createPlan();
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $pivot = $feature->plans->first()->pivot;

        $this->assertEquals(100, $pivot->charges);
        $this->assertEquals(PeriodicityType::Monthly, $pivot->periodicity_type);
        $this->assertEquals(1, $pivot->periodicity_value);
    }

    public function test_feature_plan_belongs_to_feature(): void
    {
        $plan = $this->createPlan();
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $pivot = $plan->features->first()->pivot;

        $this->assertEquals($feature->id, $pivot->feature->id);
    }

    public function test_feature_plan_belongs_to_plan(): void
    {
        $plan = $this->createPlan();
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $pivot = $plan->features->first()->pivot;

        $this->assertEquals($plan->id, $pivot->plan->id);
    }

    public function test_plan_has_many_pricings(): void
    {
        $plan = $this->createPlan();
        $this->createPlanPricing($plan, ['slug' => 'monthly']);
        $this->createPlanPricing($plan, ['slug' => 'yearly', 'periodicity_type' => PeriodicityType::Yearly, 'periodicity_value' => 1]);

        $this->assertCount(2, $plan->pricings);
    }

    public function test_plan_pricing_belongs_to_plan(): void
    {
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $this->assertEquals($plan->id, $pricing->plan->id);
    }

    public function test_subscription_change_belongs_to_subscription(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $change = $subscription->cancel()->immediately()->save();

        $this->assertEquals($subscription->id, $change->subscription->id);
    }

    public function test_subscription_feature_ticket_belongs_to_subscription(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $ticket = $subscription->giveFeatureTicket('api-calls', 50);

        $this->assertEquals($subscription->id, $ticket->subscription->id);
    }

    public function test_subscription_feature_usage_belongs_to_subscription(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $usage = $subscription->consumeFeature('api-calls', 10);

        $this->assertEquals($subscription->id, $usage->subscription->id);
    }
}
