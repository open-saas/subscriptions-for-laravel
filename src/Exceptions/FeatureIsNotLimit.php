<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use InvalidArgumentException;

class FeatureIsNotLimit extends InvalidArgumentException
{
    public function __construct(string $featureSlug)
    {
        parent::__construct("Feature with slug [{$featureSlug}] is not a limit feature. Only limit features support setting usage.");
    }
}
