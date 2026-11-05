<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin-only 7-day free trial invites. The trial is never offered on the
 * public site: the only way in is the signed, single-use link in the
 * invite email (see TrialInviteService). email is stored lower-cased so
 * "one trial per email address" can be checked exactly.
 *
 * status: sent → accepted (card saved, trial running) → converted (day-8
 * charge succeeded) or cancelled (customer cancelled the trial); an unused
 * invite becomes expired after 7 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trial_invites', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->index();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('billing_cycle');
            $table->string('status')->default('sent')->index();
            $table->foreignId('sent_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();
        });

        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->foreignId('trial_invite_id')->nullable()->after('addon_install_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payment_transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('trial_invite_id');
        });

        Schema::dropIfExists('trial_invites');
    }
};
