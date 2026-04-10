<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Events\SubscriptionCancelled;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoExpirationDate;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionChange;

class SubscriptionCancellationBuilder
{
    private bool $immediately = false;

    public function __construct(
        private readonly Subscription $subscription,
        private readonly SubscriptionChangeService $changeService,
    ) {
    }

    public function immediately(): self
    {
        $this->immediately = true;

        return $this;
    }

    public function save(): SubscriptionChange
    {
        $change = $this->changeService->recordChange($this->subscription, function () {
            if ($this->immediately) {
                $newCancelsAt = new DateTimeImmutable();
            } else {
                $newCancelsAt = $this->getScheduledCancellationDate();
            }

            $this->subscription->update([
                'cancels_at' => $newCancelsAt,
            ]);
        });

        event(new SubscriptionCancelled($this->subscription, $change));

        return $change;
    }

    private function getScheduledCancellationDate(): DateTimeImmutable
    {
        if (empty($this->subscription->expires_at)) {
            throw new SubscriptionHasNoExpirationDate('schedule cancellation');
        }

        return DateTimeImmutable::createFromInterface($this->subscription->expires_at);
    }
}
