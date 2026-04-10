<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use OpenSaas\SubscriptionsForLaravel\Enums\UsageChargesSource;

class SubscriptionFeatureUsage extends Model
{
    protected $guarded = [];
    public const null UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'expires_at' => 'datetime',
            'source' => UsageChargesSource::class,
        ];
    }

    public function feature(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$featureModelClass);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$subscriptionModelClass);
    }

    public function isNotExpired(): bool
    {
        if (empty($this->expires_at)) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function isActive(): bool
    {
        return $this->isNotExpired();
    }
}
