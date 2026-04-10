<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubscriptionsForLaravel::$planPricingModelClass);
            $table->string('subscriber_type');
            $table->string('subscriber_id');
            $table->string('slug');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('cancels_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('grace_period_ends_at')->nullable();
            $table->timestamp('starts_at');
            $table->timestamps();

            $table->index(['subscriber_type', 'subscriber_id']);
            $table->index('slug');
            $table->index('starts_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
