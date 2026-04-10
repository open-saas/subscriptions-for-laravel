<?php

namespace OpenSaas\SubscriptionsForLaravel;

use Illuminate\Support\ServiceProvider;
use OpenSaas\SubscriptionsForLaravel\Services\FeatureRegistrar;

class SubscriptionsForLaravelServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FeatureRegistrar::class);
    }

    public function boot(): void
    {
        $this->registerPublishing();
    }

    protected function registerPublishing(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishesMigrations([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'subscriptions-for-laravel-migrations');
        }
    }
}
