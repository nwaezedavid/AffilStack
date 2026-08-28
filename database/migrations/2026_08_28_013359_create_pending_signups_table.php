<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Holds a would-be account's details between "picked a plan" and
     * "payment cleared". No row in `users` is created until the matching
     * Flutterwave transaction verifies successfully — per product decision,
     * there is no way to create an account without paying for a plan.
     */
    public function up(): void
    {
        Schema::create('pending_signups', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->foreignId('plan_id')->constrained();
            $table->string('billing_cycle')->default('monthly');
            $table->string('tx_ref')->unique();
            $table->string('status')->default('pending'); // pending, completed, expired
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['email', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_signups');
    }
};
