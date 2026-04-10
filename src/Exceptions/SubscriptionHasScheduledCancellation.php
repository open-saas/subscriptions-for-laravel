<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use LogicException;

class SubscriptionHasScheduledCancellation extends LogicException
{
    public function __construct(string $action)
    {
        parent::__construct("Cannot {$action} a subscription that has a scheduled cancellation.");
    }
}
