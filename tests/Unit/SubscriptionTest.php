<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Unit;

use Carbon\Carbon;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_subscription_is_in_trial_when_trial_ends_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->trial_ends_at = Carbon::now()->addDays(5);

        $this->assertTrue($subscription->isInTrial());
    }

    public function test_subscription_is_not_in_trial_when_trial_ends_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->trial_ends_at = Carbon::now()->subDay();

        $this->assertFalse($subscription->isInTrial());
    }

    public function test_subscription_is_not_in_trial_when_trial_ends_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->trial_ends_at = null;

        $this->assertFalse($subscription->isInTrial());
    }

    public function test_subscription_is_not_expired_when_expires_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->expires_at = null;

        $this->assertTrue($subscription->isNotExpired());
    }

    public function test_subscription_is_not_expired_when_expires_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($subscription->isNotExpired());
    }

    public function test_subscription_is_expired_when_expires_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->expires_at = Carbon::now()->subDay();

        $this->assertFalse($subscription->isNotExpired());
    }

    public function test_subscription_is_in_grace_period_when_grace_period_ends_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->grace_period_ends_at = Carbon::now()->addDays(3);

        $this->assertTrue($subscription->isInGracePeriod());
    }

    public function test_subscription_is_not_in_grace_period_when_grace_period_ends_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->grace_period_ends_at = null;

        $this->assertFalse($subscription->isInGracePeriod());
    }

    public function test_subscription_is_not_in_grace_period_when_grace_period_ends_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->grace_period_ends_at = Carbon::now()->subDay();

        $this->assertFalse($subscription->isInGracePeriod());
    }

    public function test_subscription_is_cancelled_when_cancels_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = Carbon::now()->subDay();

        $this->assertTrue($subscription->isCancelled());
    }

    public function test_subscription_is_not_cancelled_when_cancels_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = null;

        $this->assertFalse($subscription->isCancelled());
    }

    public function test_subscription_is_not_cancelled_when_cancels_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = Carbon::now()->addMonth();

        $this->assertFalse($subscription->isCancelled());
    }

    public function test_subscription_has_scheduled_cancellation_when_cancels_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = Carbon::now()->addMonth();

        $this->assertTrue($subscription->hasScheduledCancellation());
    }

    public function test_subscription_has_no_scheduled_cancellation_when_cancels_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = null;

        $this->assertFalse($subscription->hasScheduledCancellation());
    }

    public function test_subscription_has_no_scheduled_cancellation_when_cancels_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->cancels_at = Carbon::now()->subDay();

        $this->assertFalse($subscription->hasScheduledCancellation());
    }

    public function test_subscription_is_started_when_starts_at_is_null(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = null;

        $this->assertTrue($subscription->isStarted());
    }

    public function test_subscription_is_started_when_starts_at_is_in_the_past(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->subDay();

        $this->assertTrue($subscription->isStarted());
    }

    public function test_subscription_is_not_started_when_starts_at_is_in_the_future(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->addDay();

        $this->assertFalse($subscription->isStarted());
    }

    public function test_subscription_is_active_when_started_and_not_cancelled(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->subDay();
        $subscription->cancels_at = null;
        $subscription->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_is_not_active_when_cancelled(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->subDay();
        $subscription->cancels_at = Carbon::now()->subHour();
        $subscription->expires_at = Carbon::now()->addMonth();

        $this->assertFalse($subscription->isActive());
    }

    public function test_subscription_is_active_during_trial(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->subDay();
        $subscription->trial_ends_at = Carbon::now()->addDays(14);
        $subscription->cancels_at = null;

        $this->assertTrue($subscription->isActive());
    }

    public function test_subscription_is_active_during_grace_period(): void
    {
        $subscription = new Subscription();
        $subscription->starts_at = Carbon::now()->subMonth();
        $subscription->expires_at = Carbon::now()->subDay();
        $subscription->grace_period_ends_at = Carbon::now()->addDays(3);
        $subscription->cancels_at = null;

        $this->assertTrue($subscription->isActive());
    }
}
