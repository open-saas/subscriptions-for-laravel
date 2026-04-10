<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use LogicException;

class SubscriptionIsNotActive extends LogicException
{
    public function __construct(string $action)
    {
        parent::__construct("Cannot {$action} a subscription that is not active.");
    }
}
