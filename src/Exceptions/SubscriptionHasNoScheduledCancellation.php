<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use LogicException;

class SubscriptionHasNoScheduledCancellation extends LogicException
{
    public function __construct()
    {
        parent::__construct('Cannot dismiss cancellation for a subscription that has no scheduled cancellation.');
    }
}
