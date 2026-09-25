<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API wallet auto-recharge: Flutterwave's tokenized-charge endpoint requires
 * the card's 2-letter country code alongside its token (see
 * FlutterwaveGateway::chargeSavedToken()) — nothing captured it until now
 * because no code ever needed to charge a saved card again without a
 * browser redirect. Nullable: a card saved before this column existed, or
 * from a gateway whose response never reports it, simply can't be used for
 * Flutterwave auto-recharge until it's saved again from a fresh payment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->string('country', 2)->nullable()->after('label');
        });
    }

    public function down(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
