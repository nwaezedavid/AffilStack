<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A "signup" transaction is created before the account exists, so
     * user_id has to be nullable until PaymentProcessor creates the User
     * on a verified payment and backfills it.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->foreignId('pending_signup_id')->nullable()->after('subscription_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_signup_id');
            $table->foreignId('user_id')->nullable(false)->change();
        });
    }
};
