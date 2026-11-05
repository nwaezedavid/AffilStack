<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sign in with Apple (required by Apple for the iPhone app because the
 * app offers Google sign-in). Single-row admin settings with the Apple
 * keys encrypted at rest (see App\Models\AppleOauthSetting), and on each
 * user the Apple account id plus the Apple refresh token (encrypted) that
 * account deletion uses to revoke the link, as Apple requires.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('apple_oauth_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('apple_id')->nullable()->unique()->after('google_id');
            $table->text('apple_refresh_token')->nullable()->after('apple_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['apple_id']);
            $table->dropColumn(['apple_id', 'apple_refresh_token']);
        });

        Schema::dropIfExists('apple_oauth_settings');
    }
};
