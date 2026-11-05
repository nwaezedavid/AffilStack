<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * - video_studio_settings: Higgsfield (the second video engine beside
 *   HeyGen) and the one-click video styles customers pick from.
 * - api_key_settings: the remaining keys and links that used to live only
 *   in the server .env (Google Places, Chrome Web Store link, Zapier
 *   invite), now entered in the admin panel.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('video_studio_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('higgsfield_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->string('higgsfield_model')->default('bytedance/seedance-2.5/reference-to-video');
            $table->string('higgsfield_resolution')->default('720p');
            $table->timestamp('higgsfield_verified_at')->nullable();
            $table->string('higgsfield_verification_status')->nullable();
            $table->text('higgsfield_verification_message')->nullable();
            $table->json('styles')->nullable();
            $table->string('heygen_default_avatar_id')->nullable();
            $table->string('heygen_default_voice_id')->nullable();
            $table->timestamps();
        });

        Schema::create('api_key_settings', function (Blueprint $table) {
            $table->id();
            $table->text('credentials')->nullable();
            $table->string('chrome_extension_store_url')->nullable();
            $table->string('zapier_invite_url')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_key_settings');
        Schema::dropIfExists('video_studio_settings');
    }
};
