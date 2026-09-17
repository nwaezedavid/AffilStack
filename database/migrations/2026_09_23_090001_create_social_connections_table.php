<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A user's own connected third-party social account (task #3: LinkedIn
     * first; task #2 — YouTube/TikTok/Instagram — reuses this same table
     * rather than one per platform). One row per user+provider; the
     * platform-specific meaning of "connected" varies (LinkedIn: identity
     * only, for export context, never posting; YouTube/TikTok/Instagram:
     * real publish-capable tokens once built) — see each provider's own
     * OAuth service for what $credentials actually holds.
     */
    public function up(): void
    {
        Schema::create('social_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('provider'); // 'linkedin' | 'youtube' | 'tiktok' | 'instagram'
            $table->json('credentials')->nullable(); // encrypted at rest — see SocialConnection casts
            $table->string('account_name')->nullable();
            $table->string('account_id')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('social_connections');
    }
};
