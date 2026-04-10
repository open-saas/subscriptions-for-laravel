<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use LogicException;

class SubscriptionAlreadyCancelled extends LogicException
{
    public function __construct(string $action)
    {
        parent::__construct("Cannot {$action} a subscription that is already cancelled.");
    }
}
