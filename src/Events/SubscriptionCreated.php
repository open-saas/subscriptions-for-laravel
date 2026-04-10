<?php

namespace OpenSaas\SubscriptionsForLaravel\Events;

use OpenSaas\SubscriptionsForLaravel\Subscription;

class SubscriptionCreated
{
    public function __construct(
        public readonly Subscription $subscription,
    ) {
    }
}
