<?php

namespace OpenSaas\SubscriptionsForLaravel\Events;

use OpenSaas\SubscriptionsForLaravel\Subscription;

class SubscriptionSwitched
{
    public function __construct(
        public readonly Subscription $oldSubscription,
        public readonly Subscription $newSubscription,
    ) {
    }
}
