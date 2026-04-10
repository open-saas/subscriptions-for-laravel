<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('subscription_feature_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubscriptionsForLaravel::$featureModelClass);
            $table->foreignIdFor(SubscriptionsForLaravel::$subscriptionModelClass);
            $table->timestamp('expires_at')->nullable();
            $table->decimal('charges')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_feature_tickets');
    }
};
