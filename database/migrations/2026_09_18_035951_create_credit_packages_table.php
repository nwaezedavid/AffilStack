<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // "Users should be able to buy more credit tokens if their monthly
        // allocation finishes" — a one-time purchase on top of a plan's
        // recurring monthly grant, priced well above the ~$0.008/credit
        // blended AI cost estimate in PlansSeeder but at a discount per
        // credit for the larger packages, the same volume-curve shape as
        // the plan tiers themselves. See CreditPackagesSeeder for the
        // actual numbers and CreditTopupController for the checkout flow.
        Schema::create('credit_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('credits');
            $table->unsignedInteger('price_cents');
            $table->string('currency', 3)->default('USD');
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_packages');
    }
};
