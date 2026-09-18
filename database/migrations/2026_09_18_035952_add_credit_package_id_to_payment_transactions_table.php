<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            // Only set when type = 'credit_topup' (see the type column's
            // original migration comment, which already anticipated this
            // value). The transaction row itself IS the purchase record —
            // no separate purchases table — so PaymentProcessor::process()
            // just reads this to know how many credits to grant once the
            // payment verifies. See CreditTopupController::checkout().
            $table->foreignId('credit_package_id')->nullable()->after('pending_signup_id')->constrained()->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('credit_package_id');
        });
    }
};
