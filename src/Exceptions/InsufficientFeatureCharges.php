<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use InvalidArgumentException;

class InsufficientFeatureCharges extends InvalidArgumentException
{
    public function __construct(string $featureSlug, float $amount)
    {
        parent::__construct("Cannot consume [{$amount}] of feature with slug [{$featureSlug}]: insufficient charges.");
    }
}
