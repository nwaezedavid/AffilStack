<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit item #8 — proof a signing-up user actually accepted the Refund &
 * Cancellation Policy before payment, not just that a checkbox exists in
 * the form. Copied onto the User row itself once the account is created —
 * see PaymentProcessor::completeSignup().
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->timestamp('refund_policy_accepted_at')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->dropColumn('refund_policy_accepted_at');
        });
    }
};
