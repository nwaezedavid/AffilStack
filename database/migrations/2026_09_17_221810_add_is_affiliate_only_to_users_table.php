<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Users are automatically affiliates, but others will have to
            // be manually approved by the admin" — a normal customer never
            // has this set; it's only ever true for an account created by
            // AffiliateApplicationService::approve() from a public affiliate
            // application. See RestrictAffiliateOnlyAccounts /
            // config('referrals.affiliate_only_allowed_routes') for how it's
            // enforced: such an account can only ever reach /referrals,
            // /profile, and logout — never any content/billing feature.
            $table->boolean('is_affiliate_only')->default(false)->after('seat_role');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_affiliate_only');
        });
    }
};
