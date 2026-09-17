<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit gap #6: a refund/chargeback clawback (see
     * ReferralService::reverseCommission()) is recorded as its own
     * ReferralEvent row with a negative amount_cents, netting against the
     * referrer's future balance rather than rewriting a payout that already
     * went out. amount_cents was unsigned, which either rejects a negative
     * value outright or silently clamps it depending on the database driver
     * — signed is required for the clawback row to store its actual value.
     */
    public function up(): void
    {
        Schema::table('referral_events', function (Blueprint $table) {
            $table->integer('amount_cents')->change();
        });
    }

    public function down(): void
    {
        Schema::table('referral_events', function (Blueprint $table) {
            $table->unsignedInteger('amount_cents')->change();
        });
    }
};
