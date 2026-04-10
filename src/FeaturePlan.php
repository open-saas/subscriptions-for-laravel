<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;

class FeaturePlan extends Pivot
{
    protected $guarded = [];
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'charges' => 'float',
            'periodicity_type' => PeriodicityType::class,
            'periodicity_value' => 'integer',
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$featureModelClass);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$planModelClass);
    }
}
