<?php

namespace OpenSaas\SubscriptionsForLaravel\Events;

use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionChange;

class SubscriptionRenewed
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionChange $change,
    ) {
    }
}
