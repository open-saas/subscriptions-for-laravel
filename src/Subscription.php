<?php

namespace OpenSaas\SubscriptionsForLaravel;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use OpenSaas\SubscriptionsForLaravel\Exceptions\FlagFeatureNotConsumable;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionAlreadyCancelled;
use OpenSaas\SubscriptionsForLaravel\Exceptions\SubscriptionHasNoScheduledCancellation;
use OpenSaas\SubscriptionsForLaravel\Enums\FeatureType;
use OpenSaas\SubscriptionsForLaravel\Events\FeatureTicketGiven;
use OpenSaas\SubscriptionsForLaravel\Events\SubscriptionCancellationDismissed;
use OpenSaas\SubscriptionsForLaravel\Services\FeatureConsumptionService;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionCancellationBuilder;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionChangeService;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionRenewalBuilder;
use OpenSaas\SubscriptionsForLaravel\Services\SubscriptionSwitchBuilder;

class Subscription extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'cancels_at' => 'datetime',
            'expires_at' => 'datetime',
            'grace_period_ends_at' => 'datetime',
            'starts_at' => 'datetime',
        ];
    }

    public function planPricing(): BelongsTo
    {
        return $this->belongsTo(SubscriptionsForLaravel::$planPricingModelClass)->withTrashed();
    }

    public function plan(): HasOneThrough
    {
        return $this->hasOneThrough(
            SubscriptionsForLaravel::$planModelClass,
            SubscriptionsForLaravel::$planPricingModelClass,
            'id',
            'id',
            'plan_pricing_id',
            'plan_id',
        )->withTrashed();
    }

    public function subscriber(): MorphTo
    {
        return $this->morphTo();
    }

    public function featureUsages(): HasMany
    {
        return $this->hasMany(SubscriptionsForLaravel::$subscriptionFeatureUsageModelClass);
    }

    public function featureTickets(): HasMany
    {
        return $this->hasMany(SubscriptionsForLaravel::$subscriptionFeatureTicketModelClass);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(SubscriptionsForLaravel::$subscriptionChangeModelClass);
    }

    public function cancel(): SubscriptionCancellationBuilder
    {
        return app()->make(SubscriptionCancellationBuilder::class, ['subscription' => $this]);
    }

    public function dismissScheduledCancellation(): SubscriptionChange
    {
        if (empty($this->cancels_at)) {
            throw new SubscriptionHasNoScheduledCancellation();
        }

        if ($this->cancels_at->isPast()) {
            throw new SubscriptionAlreadyCancelled('dismiss cancellation for');
        }

        $change = $this->changeService()->recordChange($this, function () {
            $this->update([
                'cancels_at' => null,
            ]);
        });

        event(new SubscriptionCancellationDismissed($this, $change));

        return $change;
    }

    public function isInTrial(): bool
    {
        if (empty($this->trial_ends_at)) {
            return false;
        }

        return $this->trial_ends_at->isFuture();
    }

    public function isNotExpired(): bool
    {
        if (empty($this->expires_at)) {
            return true;
        }

        return $this->expires_at->isFuture();
    }

    public function isInGracePeriod(): bool
    {
        if (empty($this->grace_period_ends_at)) {
            return false;
        }

        return $this->grace_period_ends_at->isFuture();
    }

    public function isCancelled(): bool
    {
        if (empty($this->cancels_at)) {
            return false;
        }

        return $this->cancels_at->isPast();
    }

    public function hasScheduledCancellation(): bool
    {
        if (empty($this->cancels_at)) {
            return false;
        }

        return $this->cancels_at->isFuture();
    }

    public function isStarted(): bool
    {
        if (empty($this->starts_at)) {
            return true;
        }

        return $this->starts_at->isPast();
    }

    public function isActive(): bool
    {
        if ($this->isCancelled()) {
            return false;
        }

        if (! $this->isStarted()) {
            return false;
        }

        return $this->isInTrial()
            || $this->isNotExpired()
            || $this->isInGracePeriod();
    }

    public function hasFeature(string $featureSlug): bool
    {
        if (! $this->isActive()) {
            return false;
        }

        if ($this->plan->features->contains('slug', $featureSlug)) {
            return true;
        }

        return $this->featureTickets
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->feature->slug === $featureSlug)
            ->filter(fn (SubscriptionFeatureTicket $ticket) => $ticket->isActive())
            ->isNotEmpty();
    }

    public function getFeatureUsage(string $featureSlug): float
    {
        return $this->featureConsumptionService()->getUsage($this, $featureSlug);
    }

    public function getFeatureRemainder(string $featureSlug): float
    {
        return $this->featureConsumptionService()->getRemainder($this, $featureSlug);
    }

    public function getFeatureChargesFromPlan(Feature $feature): float
    {
        return $this->featureConsumptionService()->getChargesFromPlan($this, $feature);
    }

    public function getFeatureChargesFromTickets(Feature $feature): float
    {
        return $this->featureConsumptionService()->getChargesFromTickets($this, $feature);
    }

    public function canConsumeFeature(string $featureSlug, float $amount): bool
    {
        return $this->featureConsumptionService()->canConsume($this, $featureSlug, $amount);
    }

    public function consumeFeature(string $featureSlug, float $amount): SubscriptionFeatureUsage
    {
        return $this->featureConsumptionService()->consume($this, $featureSlug, $amount);
    }

    public function giveFeatureTicket(
        string $featureSlug,
        ?float $charges,
        ?DateTimeInterface $expiresAt = null,
    ): SubscriptionFeatureTicket {
        $feature = app(Services\FeatureRegistrar::class)->findBySlug($featureSlug, $this->getConnectionName());
        if ($feature->type === FeatureType::Flag && ! empty($charges)) {
            throw new FlagFeatureNotConsumable($featureSlug, 'tickets with charges');
        }

        /** @var SubscriptionFeatureTicket $ticket */
        $ticket = $this->featureTickets()->make();
        $ticket->feature()->associate($feature);
        $ticket->fill([
            'charges' => $charges,
            'expires_at' => $expiresAt,
        ]);

        $ticket->save();

        event(new FeatureTicketGiven($this, $ticket));

        $this->unsetRelation('featureTickets');

        return $ticket;
    }

    public function renew(): SubscriptionRenewalBuilder
    {
        return app()->make(SubscriptionRenewalBuilder::class, ['subscription' => $this]);
    }

    public function switchTo(PlanPricing $newPlanPricing): SubscriptionSwitchBuilder
    {
        return app()->make(SubscriptionSwitchBuilder::class, [
            'currentSubscription' => $this,
            'newPlanPricing' => $newPlanPricing,
        ]);
    }

    public function setFeatureUsage(string $featureSlug, float $amount): SubscriptionFeatureUsage
    {
        return $this->featureConsumptionService()->setUsage($this, $featureSlug, $amount);
    }

    public function scopeStarted(Builder $query): void
    {
        $query->where('starts_at', '<=', now());
    }

    public function scopeNotStarted(Builder $query): void
    {
        $query->where('starts_at', '>', now());
    }

    public function scopeCancelled(Builder $query): void
    {
        $query->whereNotNull('cancels_at')
            ->where('cancels_at', '<=', now());
    }

    public function scopeNotCancelled(Builder $query): void
    {
        $query->where(function (Builder $q) {
            $q->whereNull('cancels_at')
                ->orWhere('cancels_at', '>', now());
        });
    }

    public function scopeInTrial(Builder $query): void
    {
        $query->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '>', now());
    }

    public function scopeInGracePeriod(Builder $query): void
    {
        $query->where('expires_at', '<=', now())
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '>', now());
    }

    public function scopeExpired(Builder $query): void
    {
        $query->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where(function (Builder $q) {
                $q->whereNull('grace_period_ends_at')
                    ->orWhere('grace_period_ends_at', '<=', now());
            })
            ->where(function (Builder $q) {
                $q->whereNull('trial_ends_at')
                    ->orWhere('trial_ends_at', '<=', now());
            });
    }

    public function scopeActive(Builder $query): void
    {
        $query->started()
            ->notCancelled()
            ->where(function (Builder $q) {
                $q->where(function (Builder $q2) {
                    $q2->inTrial();
                })->orWhere(function (Builder $q2) {
                    $q2->whereNull('expires_at')
                        ->orWhere('expires_at', '>', now());
                })->orWhere(function (Builder $q2) {
                    $q2->inGracePeriod();
                });
            });
    }

    private function changeService(): SubscriptionChangeService
    {
        return app()->make(SubscriptionChangeService::class);
    }

    private function featureConsumptionService(): FeatureConsumptionService
    {
        return app()->make(FeatureConsumptionService::class);
    }
}
