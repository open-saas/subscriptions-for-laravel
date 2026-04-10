<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use LogicException;

class SubscriptionHasNoExpirationDate extends LogicException
{
    public function __construct(string $action)
    {
        parent::__construct("Cannot {$action} for a subscription that does not have an expiration date.");
    }
}
