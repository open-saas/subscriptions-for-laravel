<?php

namespace OpenSaas\SubscriptionsForLaravel\Exceptions;

use Illuminate\Database\Eloquent\ModelNotFoundException;

class FeatureNotFound extends ModelNotFoundException
{
    public function __construct(string $slug)
    {
        parent::__construct("No feature found for slug [{$slug}].");
    }
}
