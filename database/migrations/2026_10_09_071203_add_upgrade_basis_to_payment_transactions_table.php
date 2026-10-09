<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An upgrade checkout is priced as "new plan minus the unused value of the
 * current one". These columns record which subscription (and which paid-up
 * period) that discount was worked out from, so a payment that arrives after
 * the subscription changed can't redeem the same discount again.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->unsignedBigInteger('upgrade_from_subscription_id')->nullable()->after('credited_amount_cents');
            $table->timestamp('upgrade_from_period_end')->nullable()->after('upgrade_from_subscription_id');
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropColumn(['upgrade_from_subscription_id', 'upgrade_from_period_end']);
        });
    }
};
