<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use Closure;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionChange;

class SubscriptionChangeService
{
    public function recordChange(Subscription $subscription, Closure $mutation): SubscriptionChange
    {
        $snapshot = $this->takeSnapshot($subscription);

        $mutation();

        $subscription->refresh();

        return $subscription->changes()->create([
            'old_starts_at' => $snapshot['starts_at'],
            'old_cancels_at' => $snapshot['cancels_at'],
            'old_expires_at' => $snapshot['expires_at'],
            'old_grace_period_ends_at' => $snapshot['grace_period_ends_at'],
            'new_starts_at' => $subscription->starts_at,
            'new_cancels_at' => $subscription->cancels_at,
            'new_expires_at' => $subscription->expires_at,
            'new_grace_period_ends_at' => $subscription->grace_period_ends_at,
        ]);
    }

    /**
     * @return array{starts_at: mixed, cancels_at: mixed, expires_at: mixed, grace_period_ends_at: mixed}
     */
    private function takeSnapshot(Subscription $subscription): array
    {
        return [
            'starts_at' => $subscription->starts_at,
            'cancels_at' => $subscription->cancels_at,
            'expires_at' => $subscription->expires_at,
            'grace_period_ends_at' => $subscription->grace_period_ends_at,
        ];
    }
}
