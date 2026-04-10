<?php

namespace OpenSaas\SubscriptionsForLaravel;

class SubscriptionsForLaravel
{
    public static string $defaultSubscriptionSlug = 'default';

    /** @var class-string<Plan> */
    public static string $planModelClass = Plan::class;

    /** @var class-string<Feature> */
    public static string $featureModelClass = Feature::class;

    /** @var class-string<FeaturePlan> */
    public static string $featurePlanModelClass = FeaturePlan::class;

    /** @var class-string<PlanPricing> */
    public static string $planPricingModelClass = PlanPricing::class;

    /** @var class-string<Subscription> */
    public static string $subscriptionModelClass = Subscription::class;

    /** @var class-string<SubscriptionFeatureTicket> */
    public static string $subscriptionFeatureTicketModelClass = SubscriptionFeatureTicket::class;

    /** @var class-string<SubscriptionFeatureUsage> */
    public static string $subscriptionFeatureUsageModelClass = SubscriptionFeatureUsage::class;

    /** @var class-string<SubscriptionChange> */
    public static string $subscriptionChangeModelClass = SubscriptionChange::class;
}
