<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use OpenSaas\SubscriptionsForLaravel\Enums\PeriodicityType;
use OpenSaas\SubscriptionsForLaravel\Events\SubscriptionCreated;
use OpenSaas\SubscriptionsForLaravel\Exceptions\DuplicateActiveSubscriptionForSlug;
use OpenSaas\SubscriptionsForLaravel\PlanPricing;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

class SubscriptionBuilder
{
    private ?DateTimeInterface $startsAt = null;

    private bool $skipTrial = false;

    public function __construct(
        private readonly Model $subscriber,
        private readonly PlanPricing $planPricing,
        private readonly string $slug,
        private readonly ExpirationService $expirationService,
    ) {
    }

    public function skipTrial(): self
    {
        $this->skipTrial = true;

        return $this;
    }

    public function startingAt(DateTimeInterface $startsAt): self
    {
        $this->startsAt = $startsAt;

        return $this;
    }

    public function create(): Subscription
    {
        $this->ensureSubscriberDoesNotHaveActiveSubscriptionForSlug();

        $startsAt = $this->getStartsAt();
        $trialEndsAt = $this->getTrialEndsAt($startsAt);
        $expiresAt = $this->getExpiresAt($startsAt);
        $gracePeriodEndsAt = $this->getGracePeriodEndsAt($expiresAt);

        /** @var Subscription $subscription */
        $subscription = new (SubscriptionsForLaravel::$subscriptionModelClass);
        $subscription->setConnection($this->subscriber->getConnectionName());
        $subscription->planPricing()->associate($this->planPricing);
        $subscription->subscriber()->associate($this->subscriber);
        $subscription->fill([
            'slug' => $this->slug,
            'expires_at' => $expiresAt,
            'grace_period_ends_at' => $gracePeriodEndsAt,
            'starts_at' => $startsAt,
            'trial_ends_at' => $trialEndsAt,
        ]);

        $subscription->save();

        $this->subscriber->unsetRelation('subscriptions');

        event(new SubscriptionCreated($subscription));

        return $subscription;
    }

    private function ensureSubscriberDoesNotHaveActiveSubscriptionForSlug(): void
    {
        $hasActive = $this->subscriber
            ->morphMany(SubscriptionsForLaravel::$subscriptionModelClass, 'subscriber')
            ->where('slug', $this->slug)
            ->where('starts_at', '<=', now())
            ->get()
            ->contains(fn (Subscription $subscription) => $subscription->isActive());

        if ($hasActive) {
            throw new DuplicateActiveSubscriptionForSlug($this->slug);
        }
    }

    private function getStartsAt(): DateTimeImmutable
    {
        return $this->startsAt
            ? DateTimeImmutable::createFromInterface($this->startsAt)
            : new DateTimeImmutable();
    }

    private function doesNotApplyTrial(): bool
    {
        return $this->skipTrial || $this->planPricing->doesNotHaveTrial();
    }

    private function appliesTrial(): bool
    {
        return ! $this->doesNotApplyTrial();
    }

    private function getTrialEndsAt(DateTimeImmutable $startsAt): ?DateTimeImmutable
    {
        if ($this->doesNotApplyTrial()) {
            return null;
        }

        return $this->expirationService->getExpirationFor(
            PeriodicityType::Daily,
            $this->planPricing->trial_days,
            $startsAt,
        );
    }

    private function getExpiresAt(DateTimeImmutable $startsAt): ?DateTimeImmutable
    {
        if ($this->planPricing->doesNotHavePeriodicity()) {
            return null;
        }

        if ($this->appliesTrial()) {
            return null;
        }

        return $this->expirationService->getExpirationFor(
            $this->planPricing->periodicity_type,
            $this->planPricing->periodicity_value,
            $startsAt,
        );
    }

    private function getGracePeriodEndsAt(?DateTimeImmutable $expiresAt): ?DateTimeImmutable
    {
        if (empty($expiresAt)) {
            return null;
        }

        if ($this->appliesTrial()) {
            return null;
        }

        if ($this->planPricing->doesNotHaveGracePeriod()) {
            return null;
        }

        return $this->expirationService->getExpirationFor(
            PeriodicityType::Daily,
            $this->planPricing->grace_days,
            $expiresAt,
        );
    }
}
