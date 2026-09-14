<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Set when a signup came in via "Continue with Google" (see
     * GoogleAuthController::callback) so PaymentProcessor::completeSignup()
     * can carry it onto the new User once payment verifies.
     */
    public function up(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->string('google_id')->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('pending_signups', function (Blueprint $table) {
            $table->dropColumn('google_id');
        });
    }
};
