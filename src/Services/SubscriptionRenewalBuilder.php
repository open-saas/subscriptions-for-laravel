<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionAlreadyCancelled;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoExpirationDate;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasScheduledCancellation;
use OpenSaas\SubscriptionsForLaravel\Events\SubscriptionRenewed;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionChange;

class SubscriptionRenewalBuilder
{
    private bool $restart = false;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly ExpirationService $expirationService,
        private readonly SubscriptionChangeService $changeService,
    ) {
    }

    public function restart(): self
    {
        $this->restart = true;

        return $this;
    }

    public function save(): SubscriptionChange
    {
        $change = $this->changeService->recordChange($this->subscription, function () {
            if ($this->restart) {
                $this->restartSubscription();
            } else {
                $this->renewSubscription();
            }
        });

        event(new SubscriptionRenewed($this->subscription, $change));

        return $change;
    }

    private function restartSubscription(): void
    {
        $startsAt = new DateTimeImmutable();
        $newExpiresAt = $this->subscription->planPricing->doesNotHavePeriodicity()
            ? null
            : $this->expirationService->getExpirationFor(
                $this->subscription->planPricing->periodicity_type,
                $this->subscription->planPricing->periodicity_value,
                $startsAt,
            );

        $newGracePeriodEndsAt = (empty($newExpiresAt) || $this->subscription->planPricing->doesNotHaveGracePeriod())
            ? null
            : $this->expirationService->getExpirationFor(
                PeriodicityType::Daily,
                $this->subscription->planPricing->grace_days,
                $newExpiresAt,
            );

        $this->subscription->update([
            'starts_at' => $startsAt,
            'expires_at' => $newExpiresAt,
            'grace_period_ends_at' => $newGracePeriodEndsAt,
            'cancels_at' => null,
        ]);
    }

    private function renewSubscription(): void
    {
        if (empty($this->subscription->expires_at)) {
            throw new SubscriptionHasNoExpirationDate('renew');
        }

        if ($this->subscription->hasScheduledCancellation()) {
            throw new SubscriptionHasScheduledCancellation('renew');
        }

        if ($this->subscription->isCancelled()) {
            throw new SubscriptionAlreadyCancelled('renew');
        }

        $newExpiresAt = $this->expirationService->getExpirationFor(
            $this->subscription->planPricing->periodicity_type,
            $this->subscription->planPricing->periodicity_value,
            $this->subscription->expires_at,
        );

        $newGracePeriodEndsAt = $this->subscription->planPricing->doesNotHaveGracePeriod()
            ? null
            : $this->expirationService->getExpirationFor(
                PeriodicityType::Daily,
                $this->subscription->planPricing->grace_days,
                $newExpiresAt,
            );

        $this->subscription->update([
            'expires_at' => $newExpiresAt,
            'grace_period_ends_at' => $newGracePeriodEndsAt,
        ]);
    }
}
