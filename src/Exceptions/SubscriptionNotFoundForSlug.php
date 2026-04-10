<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use RuntimeException;

class SubscriptionNotFoundForSlug extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("No active subscription found for slug [{$slug}].");
    }
}
