<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionChange extends Model
{
    public const null UPDATED_AT = null;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_starts_at' => 'datetime',
            'old_cancels_at' => 'datetime',
            'old_expires_at' => 'datetime',
            'old_grace_period_ends_at' => 'datetime',
            'new_starts_at' => 'datetime',
            'new_cancels_at' => 'datetime',
            'new_expires_at' => 'datetime',
            'new_grace_period_ends_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$subscriptionModelClass);
    }
}
