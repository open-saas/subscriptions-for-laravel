<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use OpenSaas\SubscriptionsForLaravel\SubscriptionsForLaravel;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('feature_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignIdFor(SubscriptionsForLaravel::$featureModelClass);
            $table->foreignIdFor(SubscriptionsForLaravel::$planModelClass);
            $table->decimal('charges')->nullable();
            $table->string('periodicity_type')->nullable();
            $table->integer('periodicity_value')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feature_plan');
    }
};
