<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tracks whether the Flutterwave-only renewal reminder (task #85 —
     * subscription renewal reliability) has already gone out for the
     * current period, so the daily sweep never emails the same customer
     * twice. Cleared back to null on every successful renewal.
     */
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->timestamp('renewal_reminder_sent_at')->nullable()->after('cancel_at_period_end');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('renewal_reminder_sent_at');
        });
    }
};
