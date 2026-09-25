<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The API usage prepay wallet: a real-money balance, entirely separate from
 * credits_balance (the AI-generation credits a subscription grants monthly).
 * Cached on the user row and kept correct via ApiWalletManager's row-locked
 * transactions, mirroring credits_balance itself — api_wallet_transactions
 * is the append-only audit ledger behind it, the same relationship
 * CreditLedger has to credits_balance.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('api_wallet_balance_cents')->default(0)->after('credits_balance');
            $table->boolean('api_wallet_auto_recharge_enabled')->default(false)->after('api_wallet_balance_cents');
            $table->integer('api_wallet_auto_recharge_threshold_cents')->nullable()->after('api_wallet_auto_recharge_enabled');
            $table->integer('api_wallet_auto_recharge_amount_cents')->nullable()->after('api_wallet_auto_recharge_threshold_cents');
            // The saved card auto-recharge bills — deliberately separate
            // from a general "default payment method" (PaymentMethod::
            // is_default): a user may want their subscription on one card
            // and unattended API auto-recharges on another.
            $table->foreignId('api_wallet_payment_method_id')->nullable()->after('api_wallet_auto_recharge_amount_cents')
                ->constrained('payment_methods')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('api_wallet_payment_method_id');
            $table->dropColumn([
                'api_wallet_balance_cents',
                'api_wallet_auto_recharge_enabled',
                'api_wallet_auto_recharge_threshold_cents',
                'api_wallet_auto_recharge_amount_cents',
            ]);
        });
    }
};
