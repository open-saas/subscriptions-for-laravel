<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Feature;

use Carbon\Carbon;
use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FlagFeatureNotConsumable;
use OpenSaas\SubscriptionsForLaravel\Exceptions\InsufficientFeatureCharges;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionIsNotActive;
use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Enums\UsageChargesSource;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureUsage;

class FeatureConsumptionTest extends FeatureTestCase
{
    // -- Flag Features --

    public function test_it_checks_if_subscription_has_a_flag_feature(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'dark-mode', 'type' => FeatureType::Flag]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertTrue($subscription->hasFeature('dark-mode'));
    }

    public function test_it_returns_false_for_feature_not_in_plan(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertFalse($subscription->hasFeature('nonexistent'));
    }

    public function test_it_returns_false_for_feature_when_subscription_is_not_active(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'dark-mode', 'type' => FeatureType::Flag]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->assertFalse($subscription->hasFeature('dark-mode'));
    }

    public function test_it_cannot_consume_feature_when_subscription_is_not_active(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->expectException(SubscriptionIsNotActive::class);
        $subscription->consumeFeature('api-calls', 10);
    }

    public function test_can_consume_returns_false_when_subscription_is_not_active(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->assertFalse($subscription->canConsumeFeature('api-calls', 1));
    }

    public function test_get_remainder_returns_zero_when_subscription_is_not_active(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->assertEquals(0, $subscription->getFeatureRemainder('api-calls'));
    }

    public function test_set_feature_usage_throws_when_subscription_is_not_active(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'storage', 'type' => FeatureType::Limit]);
        $this->attachFeatureToPlan($plan, $feature, charges: 500, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->expectException(SubscriptionIsNotActive::class);
        $subscription->setFeatureUsage('storage', 100);
    }

    public function test_flag_feature_cannot_have_remainder(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'dark-mode', 'type' => FeatureType::Flag]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(FlagFeatureNotConsumable::class);
        $subscription->getFeatureRemainder('dark-mode');
    }

    // -- Consumable Features --

    public function test_it_consumes_a_feature(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $usage = $subscription->consumeFeature('api-calls', 10);

        $this->assertInstanceOf(SubscriptionFeatureUsage::class, $usage);
        $this->assertEquals(10, $usage->amount);
        $this->assertEquals(UsageChargesSource::Plan, $usage->source);
        $this->assertNotNull($usage->expires_at);
    }

    public function test_it_tracks_feature_usage(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->consumeFeature('api-calls', 30);
        $subscription->consumeFeature('api-calls', 20);

        $this->assertEquals(50, $subscription->getFeatureUsage('api-calls'));
    }

    public function test_it_calculates_feature_remainder(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->consumeFeature('api-calls', 40);

        $this->assertEquals(60, $subscription->getFeatureRemainder('api-calls'));
    }

    public function test_it_checks_if_feature_can_be_consumed(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertTrue($subscription->canConsumeFeature('api-calls', 100));
        $this->assertFalse($subscription->canConsumeFeature('api-calls', 101));
    }

    public function test_it_cannot_consume_more_than_available(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(InsufficientFeatureCharges::class);
        $subscription->consumeFeature('api-calls', 101);
    }

    public function test_remainder_is_zero_when_fully_consumed(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 50, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->consumeFeature('api-calls', 50);

        $this->assertEquals(0, $subscription->getFeatureRemainder('api-calls'));
    }

    // -- Limit Features --

    public function test_it_consumes_a_limit_feature(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'storage', 'type' => FeatureType::Limit]);
        $this->attachFeatureToPlan($plan, $feature, charges: 500, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();
        $usage = $subscription->consumeFeature('storage', 100);

        $this->assertEquals(UsageChargesSource::Plan, $usage->source);
        $this->assertNull($usage->expires_at);
    }

    public function test_limit_feature_usage_never_expires(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'storage', 'type' => FeatureType::Limit]);
        $this->attachFeatureToPlan($plan, $feature, charges: 500, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();
        $usage = $subscription->consumeFeature('storage', 50);

        $this->assertNull($usage->expires_at);
        $this->assertTrue($usage->isActive());
    }

    // -- Feature Tickets --

    public function test_it_grants_a_feature_ticket(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $ticket = $subscription->giveFeatureTicket('api-calls', 50);

        $this->assertEquals(50, $ticket->charges);
        $this->assertNull($ticket->expires_at);
        $this->assertTrue($ticket->isActive());
    }

    public function test_it_grants_a_feature_ticket_with_expiration(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $expiresAt = new DateTimeImmutable('2024-06-01');
        $ticket = $subscription->giveFeatureTicket('api-calls', 50, $expiresAt);

        $this->assertEquals('2024-06-01', $ticket->expires_at->format('Y-m-d'));
    }

    public function test_flag_feature_cannot_have_ticket_with_charges(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'dark-mode', 'type' => FeatureType::Flag]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: null, periodicityValue: null);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(FlagFeatureNotConsumable::class);
        $subscription->giveFeatureTicket('dark-mode', 10);
    }

    public function test_ticket_charges_are_included_in_remainder(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 50);

        $this->assertEquals(150, $subscription->getFeatureRemainder('api-calls'));
    }

    // -- Spillover: Plan → Tickets --

    public function test_usage_draws_from_plan_first_then_tickets(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 50, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 30);

        // Consume 50 from plan
        $usage1 = $subscription->consumeFeature('api-calls', 50);
        $this->assertEquals(UsageChargesSource::Plan, $usage1->source);

        // Next consumption should spill over to tickets
        $usage2 = $subscription->consumeFeature('api-calls', 20);
        $this->assertEquals(UsageChargesSource::Ticket, $usage2->source);

        $this->assertEquals(10, $subscription->getFeatureRemainder('api-calls'));
    }

    public function test_consumption_splits_across_plan_and_tickets(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 50, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 30);

        // Consume 30 from plan
        $subscription->consumeFeature('api-calls', 30);

        // Consume 40 — should split: 20 from plan, 20 from tickets
        $subscription->consumeFeature('api-calls', 40);

        $this->assertEquals(70, $subscription->getFeatureUsage('api-calls'));
        $this->assertEquals(10, $subscription->getFeatureRemainder('api-calls'));
    }

    // -- HasSubscriptions Trait Delegation --

    public function test_has_subscriptions_delegates_has_feature(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'dark-mode', 'type' => FeatureType::Flag]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: null, periodicityValue: null);

        $user->subscribeTo($pricing)->create();

        $this->assertTrue($user->hasFeature('dark-mode'));
    }

    public function test_has_subscriptions_delegates_consume_feature(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $user->subscribeTo($pricing)->create();

        $usage = $user->consumeFeature('api-calls', 25);

        $this->assertEquals(25, $usage->amount);
        $this->assertEquals(75, $user->getFeatureRemainder('api-calls'));
        $this->assertTrue($user->canConsumeFeature('api-calls', 75));
        $this->assertFalse($user->canConsumeFeature('api-calls', 76));
    }

    public function test_has_subscriptions_delegates_give_feature_ticket(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $user->subscribeTo($pricing)->create();

        $ticket = $user->giveFeatureTicket('api-calls', 50, null);

        $this->assertEquals(50, $ticket->charges);
    }

    // -- Multi-Ticket Consumption --

    public function test_consumption_splits_across_multiple_tickets(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 30);
        $subscription->giveFeatureTicket('api-calls', 20);
        $subscription->giveFeatureTicket('api-calls', 50);

        // Consume 60 — should draw 30 from first ticket, 20 from second, and 10 from third
        $subscription->consumeFeature('api-calls', 60);

        $this->assertEquals(60, $subscription->getFeatureUsage('api-calls'));
        $this->assertEquals(40, $subscription->getFeatureRemainder('api-calls'));

        $usages = $subscription->featureUsages()->where('source', UsageChargesSource::Ticket)->get();
        $this->assertCount(3, $usages);
        $this->assertEquals(30, $usages[0]->amount);
        $this->assertEquals(20, $usages[1]->amount);
        $this->assertEquals(10, $usages[2]->amount);
    }

    public function test_consumption_from_tickets_stops_when_amount_is_fulfilled(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 0, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 30);
        $subscription->giveFeatureTicket('api-calls', 50);

        // Consume exactly the first ticket's charges — the second ticket should not be touched
        $subscription->consumeFeature('api-calls', 30);

        $usages = $subscription->featureUsages()->where('source', UsageChargesSource::Ticket)->get();
        $this->assertCount(1, $usages);
        $this->assertEquals(30, $usages->first()->amount);
        $this->assertEquals(50, $subscription->getFeatureRemainder('api-calls'));
    }

    // -- Plan Exhaustion then Ticket Spillover --

    public function test_sequential_consumptions_exhaust_plan_then_spill_to_tickets(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 50, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 40);

        // First call: consume 40 from plan (plan has 50)
        $subscription->consumeFeature('api-calls', 40);

        // Second call: consume 30 — should split 10 from plan remainder and 20 from ticket
        $subscription->consumeFeature('api-calls', 30);

        $this->assertEquals(70, $subscription->getFeatureUsage('api-calls'));
        $this->assertEquals(20, $subscription->getFeatureRemainder('api-calls'));

        $planUsages = $subscription->featureUsages()->where('source', UsageChargesSource::Plan)->get();
        $ticketUsages = $subscription->featureUsages()->where('source', UsageChargesSource::Ticket)->get();

        $this->assertEquals(50, $planUsages->sum('amount'));
        $this->assertEquals(20, $ticketUsages->sum('amount'));
    }

    // -- Feature Not in Plan --

    public function test_consuming_feature_not_in_plan_without_tickets_fails(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(InsufficientFeatureCharges::class);
        $subscription->consumeFeature('api-calls', 10);
    }

    // -- hasFeature via Tickets --

    public function test_has_feature_returns_true_when_feature_is_only_on_ticket(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'priority-support', 'type' => FeatureType::Flag]);

        $subscription = $user->subscribeTo($pricing)->create();

        // Feature is not in the plan
        $this->assertFalse($subscription->hasFeature('priority-support'));

        // Grant a ticket for the flag feature
        $subscription->giveFeatureTicket('priority-support', null);

        $this->assertTrue($subscription->hasFeature('priority-support'));
    }

    public function test_has_feature_returns_false_when_ticket_is_expired(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'priority-support', 'type' => FeatureType::Flag]);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('priority-support', null, new DateTimeImmutable('-1 day'));

        $this->assertFalse($subscription->hasFeature('priority-support'));
    }

    // -- Charges Introspection --

    public function test_it_returns_feature_charges_from_plan(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertEquals(100, $subscription->getFeatureChargesFromPlan($feature));
    }

    public function test_it_returns_feature_charges_from_tickets(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->giveFeatureTicket('api-calls', 30);
        $subscription->giveFeatureTicket('api-calls', 20);

        $this->assertEquals(50, $subscription->getFeatureChargesFromTickets($feature));
    }

    // -- HasSubscriptions Trait: getFeatureUsage --

    public function test_has_subscriptions_delegates_get_feature_usage(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $user->subscribeTo($pricing)->create();
        $user->consumeFeature('api-calls', 25);

        $this->assertEquals(25, $user->getFeatureUsage('api-calls'));
    }

    // -- Expired Ticket/Usage Exclusion --

    public function test_expired_tickets_are_not_counted_in_remainder(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();
        // Ticket already expired
        $subscription->giveFeatureTicket('api-calls', 50, new DateTimeImmutable('-1 day'));

        // Expired ticket should not contribute
        $this->assertEquals(100, $subscription->getFeatureRemainder('api-calls'));
    }

    public function test_expired_usage_is_not_counted(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $feature = $this->createFeature(['slug' => 'api-calls', 'type' => FeatureType::Consumable]);
        $this->attachFeatureToPlan($plan, $feature, charges: 100, periodicityType: PeriodicityType::Monthly, periodicityValue: 1);

        $subscription = $user->subscribeTo($pricing)->create();

        // Create a usage record that has already expired
        SubscriptionFeatureUsage::forceCreate([
            'subscription_id' => $subscription->id,
            'feature_id' => $feature->id,
            'amount' => 30,
            'source' => UsageChargesSource::Plan,
            'expires_at' => Carbon::now()->subDay(),
        ]);

        // Expired usage should not be counted
        $this->assertEquals(0, $subscription->getFeatureUsage('api-calls'));
        $this->assertEquals(100, $subscription->getFeatureRemainder('api-calls'));
    }
}
