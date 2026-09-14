<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Generalizes subscriptions/payment_transactions from Flutterwave-only
 * columns to gateway-neutral ones, so Stripe can be added alongside
 * Flutterwave without a parallel set of stripe_* columns. Safe to run
 * before any real (non-seed) subscriptions exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('gateway')->default('flutterwave')->after('billing_cycle');
            $table->string('gateway_customer_id')->nullable()->after('gateway');
            $table->string('gateway_subscription_id')->nullable()->after('gateway_customer_id');
        });

        DB::table('subscriptions')->whereNotNull('flutterwave_customer_email')->update([
            'gateway_customer_id' => DB::raw('flutterwave_customer_email'),
        ]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['flutterwave_customer_email', 'flutterwave_tx_ref', 'flutterwave_plan_id']);
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('gateway')->default('flutterwave')->after('type');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->renameColumn('flutterwave_tx_id', 'gateway_tx_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->renameColumn('gateway_tx_id', 'flutterwave_tx_id');
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('gateway');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('flutterwave_customer_email')->nullable();
            $table->string('flutterwave_tx_ref')->nullable();
            $table->string('flutterwave_plan_id')->nullable();
        });

        DB::table('subscriptions')->whereNotNull('gateway_customer_id')->update([
            'flutterwave_customer_email' => DB::raw('gateway_customer_id'),
        ]);

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'gateway_customer_id', 'gateway_subscription_id']);
        });
    }
};
