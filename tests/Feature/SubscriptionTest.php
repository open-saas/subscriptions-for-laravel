<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Feature;

use Carbon\Carbon;
use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionAlreadyCancelled;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoExpirationDate;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoScheduledCancellation;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasScheduledCancellation;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Exceptions\DuplicateActiveSubscriptionForSlug;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionNotFoundForSlug;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionChange;

class SubscriptionTest extends FeatureTestCase
{
    public function test_it_creates_a_subscription(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertInstanceOf(Subscription::class, $subscription);
        $this->assertTrue($subscription->exists);
        $this->assertEquals($pricing->id, $subscription->plan_pricing_id);
        $this->assertEquals('default', $subscription->slug);
    }

    public function test_it_creates_a_subscription_with_custom_slug(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $subscription = $user->subscribeTo($pricing, 'premium')->create();

        $this->assertEquals('premium', $subscription->slug);
    }

    public function test_it_creates_a_subscription_with_expiration_date(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);

        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertEquals('2024-02-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_it_creates_a_subscription_with_trial(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'trial_days' => 14,
        ]);

        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertEquals('2024-01-29 10:00:00', $subscription->trial_ends_at->format('Y-m-d H:i:s'));
        $this->assertNull($subscription->expires_at);
    }

    public function test_it_creates_a_subscription_skipping_trial(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'trial_days' => 14,
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);

        $subscription = $user->subscribeTo($pricing)->skipTrial()->startingAt($startsAt)->create();

        $this->assertNull($subscription->trial_ends_at);
        $this->assertNotNull($subscription->expires_at);
        $this->assertEquals('2024-02-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_it_creates_a_subscription_with_grace_period(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'grace_days' => 5,
        ]);

        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertEquals('2024-02-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-02-20 10:00:00', $subscription->grace_period_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_it_creates_a_trial_subscription_without_grace_period_even_if_configured(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'trial_days' => 14,
            'grace_days' => 5,
        ]);

        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertNotNull($subscription->trial_ends_at);
        $this->assertNull($subscription->expires_at);
        $this->assertNull($subscription->grace_period_ends_at);
    }

    public function test_it_creates_a_subscription_with_custom_start_date(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $startsAt = new DateTimeImmutable('2024-06-01 00:00:00');
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertEquals('2024-06-01 00:00:00', $subscription->starts_at->format('Y-m-d H:i:s'));
    }

    public function test_it_creates_a_subscription_without_periodicity(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => null,
            'periodicity_value' => null,
        ]);

        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertNull($subscription->expires_at);
        $this->assertNull($subscription->grace_period_ends_at);
    }

    public function test_it_prevents_duplicate_active_subscriptions_for_same_slug(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $user->subscribeTo($pricing)->create();

        $this->expectException(DuplicateActiveSubscriptionForSlug::class);
        $user->subscribeTo($pricing)->create();
    }

    public function test_it_allows_subscriptions_with_different_slugs(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $user->subscribeTo($pricing, 'default')->create();
        $subscription = $user->subscribeTo($pricing, 'secondary')->create();

        $this->assertEquals('secondary', $subscription->slug);
        $this->assertCount(2, $user->subscriptions);
    }

    public function test_it_retrieves_active_subscription_by_slug(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $created = $user->subscribeTo($pricing)->create();
        $retrieved = $user->subscription();

        $this->assertNotNull($retrieved);
        $this->assertEquals($created->id, $retrieved->id);
    }

    public function test_it_returns_null_when_no_active_subscription(): void
    {
        $user = $this->createUser();

        $this->assertNull($user->subscription());
    }

    public function test_subscription_or_fail_throws_when_no_subscription(): void
    {
        $user = $this->createUser();

        $this->expectException(SubscriptionNotFoundForSlug::class);
        $user->consumeFeature('some-feature', 1);
    }

    // -- isActive Edge Cases --

    public function test_subscription_is_not_active_when_expired_without_grace_period(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'grace_days' => null,
        ]);
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        // expires_at is 2024-02-15, which is in the past — subscription should be inactive
        $this->assertFalse($subscription->isActive());
    }

    public function test_subscription_is_not_active_when_not_yet_started(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        $startsAt = new DateTimeImmutable('+1 month');
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $this->assertFalse($subscription->isActive());
    }

    // -- Subscription Retrieval --

    public function test_subscription_retrieval_returns_latest_by_starts_at(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);

        // Create two subscriptions with different slugs so both can be active
        $first = $user->subscribeTo($pricing, 'addon')
            ->startingAt(new DateTimeImmutable('2024-01-01 00:00:00'))
            ->create();

        $second = $user->subscribeTo($pricing, 'addon-2')
            ->startingAt(new DateTimeImmutable('2024-06-01 00:00:00'))
            ->create();

        // Manually set both to the same slug to simulate two records for the same slug
        // (e.g., one expired, one current)
        Subscription::withoutEvents(function () use ($first, $second) {
            $first->update(['slug' => 'shared']);
            $second->update(['slug' => 'shared']);
        });

        $retrieved = $user->subscription('shared');
        $this->assertEquals($second->id, $retrieved->id);
    }

    // -- Soft-Deleted Plan/Pricing --

    public function test_subscription_accesses_soft_deleted_plan_pricing(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $pricing->delete();

        $subscription->refresh();

        $this->assertNotNull($subscription->planPricing);
        $this->assertTrue($subscription->planPricing->trashed());
        $this->assertEquals($pricing->id, $subscription->planPricing->id);
    }

    public function test_subscription_accesses_soft_deleted_plan(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $plan->delete();

        $subscription->refresh();

        $this->assertNotNull($subscription->plan);
        $this->assertTrue($subscription->plan->trashed());
        $this->assertEquals($plan->id, $subscription->plan->id);
    }

    // -- Cancellation --

    public function test_it_cancels_a_subscription_immediately(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $change = $subscription->cancel()->immediately()->save();

        $subscription->refresh();

        $this->assertInstanceOf(SubscriptionChange::class, $change);
        $this->assertTrue($subscription->isCancelled());
        $this->assertNotNull($subscription->cancels_at);
    }

    public function test_it_schedules_a_cancellation_at_expiration(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();

        $change = $subscription->cancel()->save();

        $subscription->refresh();

        $this->assertTrue($subscription->hasScheduledCancellation());
        $this->assertFalse($subscription->isCancelled());
        $this->assertEquals(
            $subscription->expires_at->format('Y-m-d H:i:s'),
            $change->new_cancels_at->format('Y-m-d H:i:s'),
        );
    }

    public function test_it_records_change_when_cancelling(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $change = $subscription->cancel()->immediately()->save();

        $this->assertNull($change->old_cancels_at);
        $this->assertNotNull($change->new_cancels_at);
        $this->assertEquals($subscription->id, $change->subscription_id);
    }

    public function test_scheduling_cancellation_requires_expiration_date(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => null,
            'periodicity_value' => null,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(SubscriptionHasNoExpirationDate::class);
        $subscription->cancel()->save();
    }

    public function test_it_dismisses_scheduled_cancellation(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $subscription->cancel()->save();
        $subscription->refresh();
        $this->assertTrue($subscription->hasScheduledCancellation());

        $change = $subscription->dismissScheduledCancellation();

        $subscription->refresh();
        $this->assertFalse($subscription->hasScheduledCancellation());
        $this->assertNull($subscription->cancels_at);
        $this->assertNotNull($change->old_cancels_at);
        $this->assertNull($change->new_cancels_at);
    }

    public function test_dismissing_cancellation_fails_when_no_scheduled_cancellation(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(SubscriptionHasNoScheduledCancellation::class);
        $subscription->dismissScheduledCancellation();
    }

    public function test_dismissing_cancellation_fails_when_already_cancelled(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->expectException(SubscriptionAlreadyCancelled::class);
        $subscription->dismissScheduledCancellation();
    }

    // -- Renewal --

    public function test_it_renews_a_subscription(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $oldExpiresAt = $subscription->expires_at->copy();
        $change = $subscription->renew()->save();

        $subscription->refresh();

        $this->assertInstanceOf(SubscriptionChange::class, $change);
        $this->assertEquals('2024-03-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
        $this->assertEquals($oldExpiresAt->format('Y-m-d H:i:s'), $change->old_expires_at->format('Y-m-d H:i:s'));
    }

    public function test_it_renews_a_subscription_with_grace_period(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'grace_days' => 5,
        ]);
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        $subscription->renew()->save();
        $subscription->refresh();

        $this->assertEquals('2024-03-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
        $this->assertEquals('2024-03-20 10:00:00', $subscription->grace_period_ends_at->format('Y-m-d H:i:s'));
    }

    public function test_renewal_extends_from_expires_at_not_from_now(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();

        // First renewal: 2024-02-15 → 2024-03-15
        $subscription->renew()->save();
        $subscription->refresh();

        // Second renewal: 2024-03-15 → 2024-04-15
        $subscription->renew()->save();
        $subscription->refresh();

        $this->assertEquals('2024-04-15 10:00:00', $subscription->expires_at->format('Y-m-d H:i:s'));
    }

    public function test_renewing_fails_without_expiration_date(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => null,
            'periodicity_value' => null,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->expectException(SubscriptionHasNoExpirationDate::class);
        $subscription->renew()->save();
    }

    public function test_renewing_fails_when_subscription_has_scheduled_cancellation(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->save();
        $subscription->refresh();

        $this->assertTrue($subscription->hasScheduledCancellation());
        $this->expectException(SubscriptionHasScheduledCancellation::class);
        $subscription->renew()->save();
    }

    public function test_renewing_fails_when_subscription_is_cancelled(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->expectException(SubscriptionAlreadyCancelled::class);
        $subscription->renew()->save();
    }

    // -- Restart --

    public function test_it_restarts_a_cancelled_subscription(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->assertTrue($subscription->isCancelled());

        $change = $subscription->renew()->restart()->save();
        $subscription->refresh();

        $this->assertFalse($subscription->isCancelled());
        $this->assertNull($subscription->cancels_at);
        $this->assertNotNull($subscription->starts_at);
        $this->assertNotNull($subscription->expires_at);
    }

    public function test_it_restarts_a_subscription_without_periodicity(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => null,
            'periodicity_value' => null,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $subscription->renew()->restart()->save();
        $subscription->refresh();

        $this->assertNull($subscription->cancels_at);
        $this->assertNull($subscription->expires_at);
    }

    public function test_it_restarts_a_subscription_with_grace_period(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan, [
            'periodicity_type' => PeriodicityType::Monthly,
            'periodicity_value' => 1,
            'grace_days' => 5,
        ]);
        $subscription = $user->subscribeTo($pricing)->create();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $subscription->renew()->restart()->save();
        $subscription->refresh();

        $this->assertNotNull($subscription->expires_at);
        $this->assertNotNull($subscription->grace_period_ends_at);
        $this->assertNull($subscription->cancels_at);
    }

    public function test_restart_records_change_with_old_and_new_values(): void
    {
        $startsAt = new DateTimeImmutable('2024-01-15 10:00:00');

        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->startingAt($startsAt)->create();
        $oldStartsAt = $subscription->starts_at->copy();

        $change = $subscription->renew()->restart()->save();

        $this->assertEquals($oldStartsAt->format('Y-m-d H:i:s'), $change->old_starts_at->format('Y-m-d H:i:s'));
        $this->assertNotNull($change->new_starts_at);
        $this->assertNotEquals($oldStartsAt->format('Y-m-d H:i:s'), $change->new_starts_at->format('Y-m-d H:i:s'));
    }

    // -- Relationships --

    public function test_subscription_belongs_to_plan_pricing(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertEquals($pricing->id, $subscription->planPricing->id);
    }

    public function test_subscription_has_plan_through_pricing(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertEquals($plan->id, $subscription->plan->id);
    }

    public function test_subscription_belongs_to_subscriber(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $this->assertEquals($user->id, $subscription->subscriber->id);
        $this->assertInstanceOf($user::class, $subscription->subscriber);
    }

    public function test_subscription_tracks_changes(): void
    {
        $user = $this->createUser();
        $plan = $this->createPlan();
        $pricing = $this->createPlanPricing($plan);
        $subscription = $user->subscribeTo($pricing)->create();

        $subscription->renew()->restart()->save();
        $subscription->cancel()->immediately()->save();
        $subscription->refresh();

        $this->assertCount(2, $subscription->changes);
    }
}
