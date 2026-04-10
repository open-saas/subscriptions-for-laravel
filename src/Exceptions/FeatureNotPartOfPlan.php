<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use InvalidArgumentException;

class FeatureNotPartOfPlan extends InvalidArgumentException
{
    public function __construct(string $featureSlug)
    {
        parent::__construct("Feature with slug [{$featureSlug}] is not part of the subscription plan.");
    }
}
