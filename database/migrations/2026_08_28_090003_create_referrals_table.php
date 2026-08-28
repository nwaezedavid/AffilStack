<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per referred user (unique on referred_user_id) — mirrors the
     * TrackedLink "one resource per natural key" dedup pattern, so a user
     * can never be double-attributed to two referrers. Commission events
     * live separately in referral_events; raw link clicks live separately
     * again in referral_clicks — this table is just the relationship plus
     * lightweight lifecycle status.
     */
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referrer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('referred_user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('signed_up'); // signed_up, converted
            $table->timestamp('converted_at')->nullable();
            $table->timestamps();

            $table->index(['referrer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};
