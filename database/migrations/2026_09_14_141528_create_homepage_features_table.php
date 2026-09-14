<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Feature cards shown on the public homepage (Phase 4 foundation:
     * homepage redesign) — admin-managed via Filament so the page can grow
     * or shrink as modules are added without a code deploy. `media_type`
     * gates which of `image_path` / `youtube_url` is actually used; an image
     * upload lives on the public disk, a YouTube URL is embedded on the
     * fly (see HomepageFeature::youtubeEmbedUrl()) rather than stored as an
     * embed code, so pasting any normal youtube.com/youtu.be link just works.
     */
    public function up(): void
    {
        Schema::create('homepage_features', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('description');
            $table->string('icon')->nullable();
            $table->string('media_type')->default('none'); // none, image, youtube
            $table->string('image_path')->nullable();
            $table->string('youtube_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('homepage_features');
    }
};
