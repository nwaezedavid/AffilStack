<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature 2 (conversion & earnings tracker): each row is one payout a
     * user's affiliate network reported for a click that (usually) started
     * as one of their own cloaked links — see LinkController::redirect()
     * for how the link's code gets passed through to the network so their
     * CSV export can be matched back to tracked_link_id here. A row with no
     * match still counts toward the user's totals; it just can't be broken
     * down by offer/channel until manually assigned (EarningsController::assignOffer()).
     */
    public function up(): void
    {
        Schema::create('earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tracked_link_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('network')->default('other'); // partnerstack, impact, shareasale, other
            $table->string('source')->default('manual'); // manual, csv_import
            // The network's own conversion/transaction id, when their export
            // includes one — used to dedup a re-imported CSV. Null for manual
            // entries and for network exports with no stable per-row id.
            $table->string('external_ref')->nullable();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('USD');
            $table->string('status')->default('pending'); // pending, approved, paid — as reported by the network, not AffiliStack's own billing
            $table->date('converted_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'converted_at']);
            $table->unique(['user_id', 'network', 'external_ref']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('earnings');
    }
};
