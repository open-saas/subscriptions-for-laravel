<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use RuntimeException;

class DuplicateActiveSubscriptionForSlug extends RuntimeException
{
    public function __construct(string $slug)
    {
        parent::__construct("Subscriber already has an active subscription for slug [{$slug}].");
    }
}
