<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The customer wallet: money a customer tops up to buy credit packs and pay
 * plan renewals. Separate from wallet_transactions (the affiliate payout
 * wallet) and api_wallet_transactions (the API usage prepay wallet).
 *
 * Amounts are integers in the wallet currency's minor unit (cents, kobo).
 * customer_wallet_transactions is append-only: every movement is a new
 * signed row with the balance after it, and rows are never edited or
 * deleted (see CustomerWalletTransaction). idempotency_key is unique so a
 * single verified payment can only ever credit the wallet once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->char('currency', 3);
            $table->bigInteger('balance_minor')->default(0);
            $table->boolean('use_for_renewals')->default(true);
            $table->timestamps();
        });

        Schema::create('customer_wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_wallet_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // topup, credit_pack, renewal, subscription, trial_conversion, card_check, admin_credit, admin_refund, admin_deduction
            $table->bigInteger('amount_minor'); // signed — negative when money leaves the wallet
            $table->bigInteger('balance_after_minor');
            $table->char('currency', 3);
            $table->string('description');
            $table->text('note')->nullable();
            $table->nullableMorphs('reference');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('idempotency_key')->nullable()->unique();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_wallet_transactions');
        Schema::dropIfExists('customer_wallets');
    }
};
