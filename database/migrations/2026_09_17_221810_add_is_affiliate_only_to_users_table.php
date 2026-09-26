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
            //
            // No ->after(): it used to say after('seat_role'), but seat_role
            // is only added by a later (2026_09_21) migration. SQLite ignores
            // column positioning so it never showed up in tests, but
            // MariaDB/MySQL reject it with "Unknown column 'seat_role'".
            $table->boolean('is_affiliate_only')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_affiliate_only');
        });
    }
};
