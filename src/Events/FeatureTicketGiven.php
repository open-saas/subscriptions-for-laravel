<?php

namespace OpenSaas\SubscriptionsForLaravel\Events;

use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureTicket;

class FeatureTicketGiven
{
    public function __construct(
        public readonly Subscription $subscription,
        public readonly SubscriptionFeatureTicket $ticket,
    ) {
    }
}
