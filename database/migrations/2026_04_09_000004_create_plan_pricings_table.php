<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_pricings', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubscriptionsForLaravel::$planModelClass);
            $table->integer('grace_days')->nullable();
            $table->string('periodicity_type')->nullable();
            $table->integer('periodicity_value')->nullable();
            $table->string('slug');
            $table->integer('trial_days')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('slug');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_pricings');
    }
};
