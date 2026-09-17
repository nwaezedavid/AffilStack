<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit gap #6: a chargeback webhook (Flutterwave) identifies the
     * disputed charge by its `flw_ref`, not the numeric transaction id
     * already stored in gateway_tx_id — so without this there'd be no way
     * to match a chargeback back to the PaymentTransaction it disputes.
     * Left null for gateways/flows that don't have a separate reference
     * (Stripe matches refunds/disputes on gateway_tx_id directly).
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->string('gateway_reference')->nullable()->after('gateway_tx_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn('gateway_reference');
        });
    }
};
