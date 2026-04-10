<?php

namespace OpenSaas\SubscriptionsForLaravel\Services;

use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FeatureIsNotLimit;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FeatureNotPartOfPlan;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FlagFeatureNotConsumable;
use OpenSaas\SubscriptionsForLaravel\Exceptions\InsufficientFeatureCharges;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionIsNotActive;
use OpenSaas\SubscriptionsForLaravel\Enums\UsageChargesSource;
use OpenSaas\SubscriptionsForLaravel\Events\FeatureConsumed;
use OpenSaas\SubscriptionsForLaravel\Feature;
use OpenSaas\SubscriptionsForLaravel\Subscription;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureTicket;
use OpenSaas\SubscriptionsForLaravel\SubscriptionFeatureUsage;

class FeatureConsumptionService
{
    public function __construct(
        private readonly ExpirationService $expirationService,
        private readonly FeatureRegistrar $featureRegistrar,
    ) {
    }

    public function consume(Subscription $subscription, string $featureSlug, float $amount): SubscriptionFeatureUsage
    {
        if (! $subscription->isActive()) {
            throw new SubscriptionIsNotActive('consume feature');
        }

        if (! $this->canConsume($subscription, $featureSlug, $amount)) {
            throw new InsufficientFeatureCharges($featureSlug, $amount);
        }

        $feature = $this->featureRegistrar->findBySlug($featureSlug, $subscription->getConnectionName());

        $usage = $this->registerUsage($subscription, $feature, $amount);

        event(new FeatureConsumed($subscription, $usage));

        return $usage;
    }

    public function canConsume(Subscription $subscription, string $featureSlug, float $amount): bool
    {
        if (! $subscription->isActive()) {
            return false;
        }

        $remainder = $this->getRemainder($subscription, $featureSlug);

        return $remainder >= $amount;
    }

    public function getUsage(Subscription $subscription, string $featureSlug): float
    {
        $subscription->loadMissing(['featureUsages', 'featureUsages.feature']);

        return $subscription->featureUsages
            ->filter(fn (SubscriptionFeatureUsage $usage) => $usage->feature->slug === $featureSlug)
            ->filter(fn (SubscriptionFeatureUsage $usage) => $usage->isActive())
            ->sum('amount');
    }

    public function getRemainder(Subscription $subscription, string $featureSlug): float
    {
        if (! $subscription->isActive()) {
            return 0;
        }

        $feature = $this->featureRegistrar->findBySlug($featureSlug, $subscription->getConnectionName());
        if ($feature->type === FeatureType::Flag) {
            throw new FlagFeatureNotConsumable($featureSlug, 'remainder calculation');
        }

        $planCharges = $this->getChargesFromPlan($subscription, $feature);
        $ticketCharges = $this->getChargesFromTickets($subscription, $feature);
        $totalCharges = $planCharges + $ticketCharges;

        $currentUsage = $this->getUsage($subscription, $featureSlug);

        return max($totalCharges - $currentUsage, 0);
    }

    public function getChargesFromPlan(Subscription $subscription, Feature $feature): float
    {
        $featureFromPlan = $subscription->plan->features->firstWhere('slug', $feature->slug);
        if (empty($featureFromPlan)) {
            return 0;
        }

        return $featureFromPlan->pivot->charges;
    }

    public function getChargesFromTickets(Subscription $subscription, Feature $feature): float
    {
        $tickets = $subscription->featureTickets
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->feature_id === $feature->id)
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->isActive());

        return $tickets->sum('charges');
    }

    public function setUsage(Subscription $subscription, string $featureSlug, float $amount): SubscriptionFeatureUsage
    {
        if (! $subscription->isActive()) {
            throw new SubscriptionIsNotActive('set feature usage');
        }

        $feature = $this->featureRegistrar->findBySlug($featureSlug, $subscription->getConnectionName());

        if ($feature->type !== FeatureType::Limit) {
            throw new FeatureIsNotLimit($featureSlug);
        }

        $totalCharges = $this->getChargesFromPlan($subscription, $feature)
            + $this->getChargesFromTickets($subscription, $feature);

        if ($amount > $totalCharges) {
            throw new InsufficientFeatureCharges($featureSlug, $amount);
        }

        $subscription->featureUsages()->where('feature_id', $feature->id)->delete();

        /** @var SubscriptionFeatureUsage $usage */
        $usage = $subscription->featureUsages()->make();
        $usage->feature()->associate($feature);
        $usage->fill([
            'amount' => $amount,
            'expires_at' => null,
            'source' => UsageChargesSource::Plan,
        ]);

        $usage->save();

        event(new FeatureConsumed($subscription, $usage));

        $subscription->unsetRelation('featureUsages');

        return $usage;
    }

    private function registerUsage(Subscription $subscription, Feature $feature, float $amount): SubscriptionFeatureUsage
    {
        $currentUsage = $this->getUsage($subscription, $feature->slug);
        $chargesFromPlan = $this->getChargesFromPlan($subscription, $feature);

        if ($currentUsage + $amount <= $chargesFromPlan) {
            return $this->registerUsageFromPlan($subscription, $feature, $amount);
        }

        if ($currentUsage >= $chargesFromPlan) {
            return $this->registerUsageFromTickets($subscription, $feature, $amount);
        }

        $usageFromPlan = $chargesFromPlan - $currentUsage;
        $this->registerUsageFromPlan($subscription, $feature, $usageFromPlan);

        $usageFromTickets = $amount - $usageFromPlan;
        return $this->registerUsageFromTickets($subscription, $feature, $usageFromTickets);
    }

    private function registerUsageFromPlan(Subscription $subscription, Feature $feature, float $amount): SubscriptionFeatureUsage
    {
        $featureFromPlan = $subscription->plan->features->firstWhere('slug', $feature->slug)?->pivot;
        if (empty($featureFromPlan)) {
            throw new FeatureNotPartOfPlan($feature->slug);
        }

        $usageExpiresAt = match ($feature->type) {
            FeatureType::Consumable => $this->expirationService->getNextCyclicExpirationFor(
                periodicityType: $featureFromPlan->periodicity_type,
                periodicityValue: $featureFromPlan->periodicity_value,
                startDate: $subscription->starts_at,
            ),
            FeatureType::Limit => null,
        };

        /** @var SubscriptionFeatureUsage $usage */
        $usage = $subscription->featureUsages()->make();
        $usage->feature()->associate($feature);
        $usage->fill([
            'amount' => $amount,
            'expires_at' => $usageExpiresAt,
            'source' => UsageChargesSource::Plan,
        ]);

        $usage->save();

        $subscription->unsetRelation('featureUsages');

        return $usage;
    }

    private function registerUsageFromTickets(Subscription $subscription, Feature $feature, float $amount): SubscriptionFeatureUsage
    {
        $activeTickets = $subscription->featureTickets
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->feature_id === $feature->id)
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->isActive());

        if ($activeTickets->sum('charges') < $amount) {
            throw new InsufficientFeatureCharges($feature->slug, $amount);
        }

        $lastUsage = null;
        $remainingAmountToRegister = $amount;

        foreach ($activeTickets as $ticket) {
            if ($remainingAmountToRegister <= 0) {
                break;
            }

            $amountFromTicket = min($ticket->charges, $remainingAmountToRegister);

            /** @var SubscriptionFeatureUsage $lastUsage */
            $lastUsage = $subscription->featureUsages()->make();
            $lastUsage->feature()->associate($feature);
            $lastUsage->fill([
                'amount' => $amountFromTicket,
                'expires_at' => $ticket->expires_at,
                'source' => UsageChargesSource::Ticket,
            ]);

            $lastUsage->save();

            $remainingAmountToRegister -= $amountFromTicket;
        }

        $subscription->unsetRelation('featureUsages');

        if (empty($lastUsage)) {
            throw new InsufficientFeatureCharges($feature->slug, $amount);
        }

        return $lastUsage;
    }
}
