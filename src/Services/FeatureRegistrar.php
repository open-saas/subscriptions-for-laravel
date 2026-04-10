<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use Illuminate\Support\Collection;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FeatureNotFound;
use OpenSaas\SubscriptionsForLaravel\Feature;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

class FeatureRegistrar
{
    /** @var array<string, Collection<int, Feature>> */
    private array $features = [];

    public function findBySlug(string $slug, string $connection): Feature
    {
        return $this->getFeatures($connection)->firstWhere('slug', $slug)
            ?? throw new FeatureNotFound($slug);
    }

    private function getFeatures(string $connection): Collection
    {
        if (! isset($this->features[$connection])) {
            $this->features[$connection] = SubscriptionsForLaravel::$featureModelClass::on($connection)->get();
        }

        return $this->features[$connection];
    }
}
