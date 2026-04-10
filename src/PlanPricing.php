<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;

class PlanPricing extends Model
{
    use SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'grace_days' => 'integer',
            'periodicity_type' => PeriodicityType::class,
            'periodicity_value' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$planModelClass);
    }

    public function doesNotHavePeriodicity(): bool
    {
        return empty($this->periodicity_type) || empty($this->periodicity_value);
    }

    public function doesNotHaveTrial(): bool
    {
        return empty($this->trial_days);
    }

    public function doesNotHaveGracePeriod(): bool
    {
        return empty($this->grace_days);
    }
}
