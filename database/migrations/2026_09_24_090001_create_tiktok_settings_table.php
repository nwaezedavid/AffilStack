<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #2 (TikTok publishing): single-row (id=1) admin configuration
     * for a TikTok developer app's OAuth client (client_key/client_secret).
     * approval_status tracks TikTok's own Content Posting API audit — every
     * new app defaults to a capped/private "share to draft" scope until
     * that audit passes, so this gates whether AffilStack even attempts a
     * real publish vs. falling back to download-and-post-manually — see
     * App\Services\Social\TikTokPublishingService.
     */
    public function up(): void
    {
        Schema::create('tiktok_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->json('credentials')->nullable();
            $table->string('approval_status')->default('not_submitted');
            $table->text('approval_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tiktok_settings');
    }
};
