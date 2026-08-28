<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Captured at RegistrationController::store() time from the referral
     * cookie (see ReferralService::attachReferrerToPendingSignup), then
     * turned into a Referral row once the account actually gets created —
     * see PaymentProcessor::completeSignup().
     */
    public function up(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->foreignId('referred_by_user_id')->nullable()->after('email')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->dropConstrainedForeignId('referred_by_user_id');
        });
    }
};
