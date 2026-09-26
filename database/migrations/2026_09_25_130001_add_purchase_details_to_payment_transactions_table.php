<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pins what was actually bought onto the transaction row the server created
 * at checkout time, so PaymentProcessor never has to trust gateway-side
 * metadata for it:
 *
 *  - plan_id / billing_cycle: previously read back from the gateway's
 *    echoed metadata, which a client-initiated checkout on some gateways
 *    can set itself.
 *  - credited_amount_cents: the value to credit in the platform's own
 *    currency. amount_cents is what the gateway charged, which for
 *    Paystack is naira kobo — crediting that to a USD-cent API wallet
 *    over-credited by the exchange rate (~1,600x).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('billing_cycle', 10)->nullable();
            $table->unsignedInteger('credited_amount_cents')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['billing_cycle', 'credited_amount_cents']);
        });
    }
};
