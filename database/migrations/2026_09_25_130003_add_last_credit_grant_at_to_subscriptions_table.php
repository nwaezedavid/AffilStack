<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Plans are sold as "N AI credits / month", but a yearly subscription only
 * ever received one grant, at purchase — a yearly customer got a twelfth of
 * what they paid for. This timestamp lets subscriptions:grant-monthly-credits
 * top a yearly subscription up once a month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('last_credit_grant_at')->nullable();
        });

        // Existing subscriptions were last granted when their period began.
        DB::table('subscriptions')->whereNull('last_credit_grant_at')
            ->update(['last_credit_grant_at' => DB::raw('current_period_start')]);
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('last_credit_grant_at');
        });
    }
};
