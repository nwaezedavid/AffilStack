<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phone app phase 5: push notifications and links that open the app.
 * A single-row admin settings table (Site > App Push & Links) holding the
 * Expo access token (encrypted), the Apple Team ID and the Android signing
 * fingerprints the two .well-known app-link files need; and on each user
 * the notification types they switched off in the app.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_integration_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('push_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->string('ios_team_id', 10)->nullable();
            $table->text('android_sha256_fingerprints')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->json('push_preferences')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('push_preferences');
        });

        Schema::dropIfExists('mobile_integration_settings');
    }
};
