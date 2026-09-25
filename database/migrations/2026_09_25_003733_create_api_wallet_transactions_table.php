<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only audit ledger behind users.api_wallet_balance_cents — mirrors
 * credit_ledger's own shape/role for credits_balance. Every row is one
 * signed movement (positive for a top-up/refund/auto-recharge, negative for
 * a metered API call) plus the resulting balance, so an admin (or the user,
 * on their own wallet history) can always reconcile the running total
 * without recomputing it from scratch. Never edited or deleted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // topup, usage, refund, auto_recharge
            $table->integer('amount_cents'); // signed — negative for a metered API call
            $table->integer('balance_after_cents');
            $table->string('description')->nullable();
            $table->nullableMorphs('reference'); // e.g. the ApiToken or Offer a usage charge was for
            $table->timestamps();

            $table->index(['user_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_wallet_transactions');
    }
};
