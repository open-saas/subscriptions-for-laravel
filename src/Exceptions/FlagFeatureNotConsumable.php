<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use InvalidArgumentException;

class FlagFeatureNotConsumable extends InvalidArgumentException
{
    public function __construct(string $featureSlug, string $action)
    {
        parent::__construct("Feature with slug [{$featureSlug}] is a flag, it cannot be used for {$action}.");
    }
}
