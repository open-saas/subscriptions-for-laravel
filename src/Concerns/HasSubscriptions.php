<?php

namespace OpenSaas\SubscriptionsForLaravel\Concerns;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionNotFoundForSlug;
use OpenSaas\SubscriptionsForLaravel\PlanPricing;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionBuilder;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionSwitchBuilder;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureTicket;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureUsage;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

/**
 * @mixin Model
 */
trait HasSubscriptions
{
    public function subscriptions(): MorphMany
    {
        return $this->morphMany(SubscriptionsForLaravel::$subscriptionModelClass, 'subscriber');
    }

    public function subscription(?string $slug = null): ?Subscription
    {
        $slug ??= SubscriptionsForLaravel::$defaultSubscriptionSlug;

        $this->loadMissing('subscriptions');

        return $this->subscriptions
            ->where('slug', $slug)
            ->where('starts_at', '<=', now())
            ->sortByDesc('starts_at')
            ->first();
    }

    public function subscribeTo(
        PlanPricing $planPricing,
        ?string $slug = null,
    ): SubscriptionBuilder {
        $slug ??= SubscriptionsForLaravel::$defaultSubscriptionSlug;

        return app()->make(SubscriptionBuilder::class, [
            'subscriber' => $this,
            'planPricing' => $planPricing,
            'slug' => $slug,
        ]);
    }

    public function hasFeature(string $featureSlug, ?string $subscriptionSlug = null): bool
    {
        return $this->subscription($subscriptionSlug)?->hasFeature($featureSlug) ?? false;
    }

    public function getFeatureUsage(string $featureSlug, ?string $subscriptionSlug = null): float
    {
        return $this->subscription($subscriptionSlug)?->getFeatureUsage($featureSlug) ?? 0;
    }

    public function getFeatureRemainder(string $featureSlug, ?string $subscriptionSlug = null): float
    {
        return $this->subscription($subscriptionSlug)?->getFeatureRemainder($featureSlug) ?? 0;
    }

    public function canConsumeFeature(string $featureSlug, float $amount, ?string $subscriptionSlug = null): bool
    {
        return $this->subscription($subscriptionSlug)?->canConsumeFeature($featureSlug, $amount) ?? false;
    }

    public function consumeFeature(string $featureSlug, float $amount, ?string $subscriptionSlug = null): SubscriptionFeatureUsage
    {
        return $this->subscriptionOrFail($subscriptionSlug)->consumeFeature($featureSlug, $amount);
    }

    public function giveFeatureTicket(
        string             $featureSlug,
        float              $amount,
        ?DateTimeInterface $expiresAt,
        ?string            $subscriptionSlug = null,
    ): SubscriptionFeatureTicket {
        return $this->subscriptionOrFail($subscriptionSlug)->giveFeatureTicket($featureSlug, $amount, $expiresAt);
    }

    public function switchTo(
        PlanPricing $newPlanPricing,
        ?string $slug = null,
    ): SubscriptionSwitchBuilder {
        return $this->subscriptionOrFail($slug)->switchTo($newPlanPricing);
    }

    public function setFeatureUsage(string $featureSlug, float $amount, ?string $subscriptionSlug = null): SubscriptionFeatureUsage
    {
        return $this->subscriptionOrFail($subscriptionSlug)->setFeatureUsage($featureSlug, $amount);
    }

    public function hasSubscriptionTo(PlanPricing $planPricing, ?string $slug = null): bool
    {
        $slug ??= SubscriptionsForLaravel::$defaultSubscriptionSlug;

        $subscription = $this->subscription($slug);

        if ($subscription === null) {
            return false;
        }

        if (! $subscription->isActive()) {
            return false;
        }

        return $subscription->plan_pricing_id === $planPricing->id;
    }

    public function isSubscribedTo(PlanPricing $planPricing, ?string $slug = null): bool
    {
        return $this->hasSubscriptionTo($planPricing, $slug);
    }

    public function lastSubscription(?string $slug = null): ?Subscription
    {
        $slug ??= SubscriptionsForLaravel::$defaultSubscriptionSlug;

        $this->loadMissing('subscriptions');

        return $this->subscriptions
            ->where('slug', $slug)
            ->sortByDesc('starts_at')
            ->first();
    }

    private function subscriptionOrFail(?string $slug): Subscription
    {
        $slug ??= SubscriptionsForLaravel::$defaultSubscriptionSlug;

        return $this->subscription($slug) ?? throw new SubscriptionNotFoundForSlug($slug);
    }
}
