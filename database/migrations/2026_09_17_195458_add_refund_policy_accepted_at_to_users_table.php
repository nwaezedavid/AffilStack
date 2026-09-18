<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit item #8 — carried over from PendingSignup once the account is
 * created (see PaymentProcessor::completeSignup()). Nullable because every
 * account created before this feature shipped never accepted anything —
 * RefundEligibilityService treats a null timestamp the same as any other
 * account, since eligibility is otherwise driven entirely by the 48-hour/
 * zero-usage rule, not by whether this column is set.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('refund_policy_accepted_at')->nullable()->after('email_verified_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('refund_policy_accepted_at');
        });
    }
};
