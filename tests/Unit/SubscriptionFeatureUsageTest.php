<?php

namespace OpenSaas\SubscriptionsForLaravel\Tests\Unit;

use Carbon\Carbon;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureUsage;
use OpenSaas\SubscriptionsForLaravel\Tests\TestCase;

class SubscriptionFeatureUsageTest extends TestCase
{
    public function test_usage_is_not_expired_when_expires_at_is_null(): void
    {
        $usage = new SubscriptionFeatureUsage();
        $usage->expires_at = null;

        $this->assertTrue($usage->isNotExpired());
    }

    public function test_usage_is_not_expired_when_expires_at_is_in_the_future(): void
    {
        $usage = new SubscriptionFeatureUsage();
        $usage->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($usage->isNotExpired());
    }

    public function test_usage_is_expired_when_expires_at_is_in_the_past(): void
    {
        $usage = new SubscriptionFeatureUsage();
        $usage->expires_at = Carbon::now()->subDay();

        $this->assertFalse($usage->isNotExpired());
    }

    public function test_usage_is_active_when_not_expired(): void
    {
        $usage = new SubscriptionFeatureUsage();
        $usage->expires_at = Carbon::now()->addMonth();

        $this->assertTrue($usage->isActive());
    }

    public function test_usage_is_not_active_when_expired(): void
    {
        $usage = new SubscriptionFeatureUsage();
        $usage->expires_at = Carbon::now()->subDay();

        $this->assertFalse($usage->isActive());
    }
}
