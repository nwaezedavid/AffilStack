<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The affiliate payout wallet (audit item #5) — an admin-funded ledger this
 * app draws from to auto-disburse approved ReferralPayouts, instead of the
 * admin having to move money manually from a gateway's own dashboard. This
 * is a ledger, not a mutable balance column: every row is one signed
 * movement (positive for a top-up, negative for a disbursement/adjustment),
 * and the current balance is always SUM(amount_cents) — see
 * PayoutWalletService::balance(). Immutable once written; nothing here is
 * ever edited or deleted, only appended to.
 *
 * Originally timestamped 2026_09_17, before referral_payouts existed — fine
 * on SQLite (which doesn't validate a foreign key's target table at create
 * time) but a hard "errno 150" failure on MariaDB/MySQL, which does. Moved
 * after 2026_09_20_090001_create_referral_payouts_table; the hasTable()
 * guard keeps any database that already ran it under the old name working.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('wallet_transactions')) {
            return;
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('currency');
            $table->string('type'); // top_up, payout, adjustment
            $table->integer('amount_cents'); // signed — negative for a disbursement
            $table->string('gateway')->nullable(); // flutterwave, paypal — set on a payout row
            $table->foreignId('referral_payout_id')->nullable()->constrained()->nullOnDelete();
            $table->string('reference')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['currency', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
