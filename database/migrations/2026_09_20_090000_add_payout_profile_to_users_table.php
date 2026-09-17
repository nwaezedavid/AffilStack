<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The referral program overhaul's payout profile: where an affiliate
     * wants their commission sent. payout_details is encrypted at rest
     * (same convention as HeyGenSetting/BrainAgentSetting credentials) —
     * it can hold a PayPal email or bank account details depending on
     * payout_method. See User::hasPayoutMethodOnFile() and
     * ReferralPayoutService.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('payout_method')->nullable()->after('referral_code');
            $table->text('payout_details')->nullable()->after('payout_method');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['payout_method', 'payout_details']);
        });
    }
};
