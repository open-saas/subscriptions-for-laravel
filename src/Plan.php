<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    public function features(): BelongsToMany
    {
        return $this->belongsToMany(SubscriptionsForLaravel::$featureModelClass)
            ->withPivot([
                'charges',
                'periodicity_type',
                'periodicity_value',
            ])
            ->using(SubscriptionsForLaravel::$featurePlanModelClass);
    }

    public function pricings(): HasMany
    {
        return $this->hasMany(SubscriptionsForLaravel::$planPricingModelClass);
    }
}
