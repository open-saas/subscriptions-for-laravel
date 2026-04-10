<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use DateTimeImmutable;
use OpenSaas\SubscriptionsForLaravel\Events\SubscriptionSwitched;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoExpirationDate;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionIsNotActive;
use OpenSaas\SubscriptionsForLaravel\PlanPricing;
use OpenSaas\SubscriptionsForLaravel\Subscription;

class SubscriptionSwitchBuilder
{
    private bool $immediately = false;

    private bool $skipTrial = false;

    public function __construct(
        private readonly Subscription $currentSubscription,
        private readonly PlanPricing $newPlanPricing,
        private readonly SubscriptionChangeService $changeService,
    ) {
    }

    public function immediately(): self
    {
        $this->immediately = true;

        return $this;
    }

    public function skipTrial(): self
    {
        $this->skipTrial = true;

        return $this;
    }

    public function create(): Subscription
    {
        if (! $this->currentSubscription->isActive()) {
            throw new SubscriptionIsNotActive('switch');
        }

        if ($this->immediately) {
            $newSubscription = $this->switchImmediately();
        } else {
            $newSubscription = $this->switchDeferred();
        }

        event(new SubscriptionSwitched($this->currentSubscription, $newSubscription));

        return $newSubscription;
    }

    private function switchImmediately(): Subscription
    {
        $this->changeService->recordChange($this->currentSubscription, function () {
            $this->currentSubscription->update([
                'cancels_at' => new DateTimeImmutable(),
            ]);
        });

        return $this->createNewSubscription();
    }

    private function switchDeferred(): Subscription
    {
        if (empty($this->currentSubscription->expires_at)) {
            throw new SubscriptionHasNoExpirationDate('schedule a deferred switch');
        }

        $this->changeService->recordChange($this->currentSubscription, function () {
            $this->currentSubscription->update([
                'cancels_at' => DateTimeImmutable::createFromInterface($this->currentSubscription->expires_at),
            ]);
        });

        return $this->createNewSubscription(
            startingAt: $this->currentSubscription->expires_at,
        );
    }

    private function createNewSubscription(?\DateTimeInterface $startingAt = null): Subscription
    {
        $builder = app()->make(SubscriptionBuilder::class, [
            'subscriber' => $this->currentSubscription->subscriber,
            'planPricing' => $this->newPlanPricing,
            'slug' => $this->currentSubscription->slug,
        ]);

        if ($this->skipTrial) {
            $builder->skipTrial();
        }

        if ($startingAt !== null) {
            $builder->startingAt($startingAt);
        }

        return $builder->create();
    }
}
