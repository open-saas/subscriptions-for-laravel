<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubscriptionsForLaravel::$subscriptionModelClass);
            $table->timestamp('old_starts_at')->nullable();
            $table->timestamp('old_cancels_at')->nullable();
            $table->timestamp('old_expires_at')->nullable();
            $table->timestamp('old_grace_period_ends_at')->nullable();
            $table->timestamp('new_starts_at')->nullable();
            $table->timestamp('new_cancels_at')->nullable();
            $table->timestamp('new_expires_at')->nullable();
            $table->timestamp('new_grace_period_ends_at')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_changes');
    }
};
