<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Links a commission event to the payout batch it was paid out in.
     * Null means "approved but not yet claimed by a payout request" (or
     * still pending/rejected). See ReferralPayoutService::requestPayout().
     */
    public function up(): void
    {
        Schema::table('referral_events', function (Blueprint $table) {
            $table->foreignId('referral_payout_id')->nullable()->after('status')->constrained('referral_payouts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('referral_events', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referral_payout_id');
        });
    }
};
