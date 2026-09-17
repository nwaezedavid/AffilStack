<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The referral program overhaul's real payout workflow: a batch of an
     * affiliate's approved-but-unpaid ReferralEvents, requested once they
     * clear config('referrals.minimum_payout_cents'). payout_method/
     * payout_details are snapshotted from the user's profile at request
     * time (encrypted, like the profile fields) so a later profile change
     * never affects a payout already in flight. See
     * App\Services\Referrals\ReferralPayoutService.
     */
    public function up(): void
    {
        Schema::create('referral_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('requested'); // requested, paid, rejected
            $table->string('payout_method');
            $table->text('payout_details');
            $table->string('reference')->nullable(); // admin-entered payment reference (e.g. PayPal transaction id)
            $table->text('note')->nullable(); // admin note, e.g. a rejection reason
            $table->timestamp('requested_at');
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referral_payouts');
    }
};
