<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('price_monthly_cents');
            $table->unsignedInteger('price_yearly_cents');
            $table->string('currency', 3)->default('USD');
            $table->unsignedInteger('credits_per_month');
            $table->unsignedInteger('active_products_limit')->default(1);
            $table->unsignedInteger('contact_limit');
            $table->unsignedInteger('team_seats')->default(1);
            $table->json('channels')->nullable();
            $table->json('features')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
