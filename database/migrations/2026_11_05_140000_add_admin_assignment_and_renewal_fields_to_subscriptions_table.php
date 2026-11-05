<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-assigned complimentary months, invite-only trials and wallet-first
 * renewals. assigned_by_admin marks a free month an admin gave from the
 * Users screen (see AdminPlanAssigner); payment_method_id is the saved card
 * a renewal or trial conversion charges after the customer wallet (see
 * SubscriptionRenewalCharger); renewal_attempted_at stops the hourly charge
 * run retrying a declined card every hour; trial_reminder_sent_at makes the
 * "your trial ends in 2 days" email go out once.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->boolean('assigned_by_admin')->default(false)->after('gateway_subscription_id');
            $table->foreignId('assigned_by_user_id')->nullable()->after('assigned_by_admin')->constrained('users')->nullOnDelete();
            $table->foreignId('payment_method_id')->nullable()->after('assigned_by_user_id')->constrained('payment_methods')->nullOnDelete();
            $table->timestamp('trial_reminder_sent_at')->nullable()->after('trial_ends_at');
            $table->timestamp('renewal_attempted_at')->nullable()->after('renewal_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_by_user_id');
            $table->dropConstrainedForeignId('payment_method_id');
            $table->dropColumn(['assigned_by_admin', 'trial_reminder_sent_at', 'renewal_attempted_at']);
        });
    }
};
