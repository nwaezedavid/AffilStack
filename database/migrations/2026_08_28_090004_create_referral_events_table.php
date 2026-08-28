<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The commission ledger — one row per commissionable payment, kept
     * separate from referral_clicks because clicks aren't revenue events.
     * Admins move status pending -> approved -> paid from the Filament
     * resource (mirrors SupportTicketsTable's inline SelectColumn pattern).
     */
    public function up(): void
    {
        Schema::create('referral_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_type'); // first_payment, renewal
            $table->unsignedInteger('amount_cents'); // commission owed, not the payment amount
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, approved, paid
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['referral_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_events');
    }
};
