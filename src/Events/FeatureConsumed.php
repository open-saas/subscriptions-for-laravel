<?php

namespace OpenSaas\SubscriptionsForLaravel\Events;

use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureUsage;

class FeatureConsumed
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionFeatureUsage $usage,
    ) {
    }
}
