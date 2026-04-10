<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;

class Feature extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => FeatureType::class,
        ];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionsForLaravel::$planModelClass)
            ->withPivot([
                'charges',
                'periodicity_type',
                'periodicity_value',
            ])
            ->using(SubscriptionsForLaravel::$featurePlanModelClass);
    }
}
