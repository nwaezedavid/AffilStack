<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The official AffilStack phone apps: one row per signed-in app install
 * (see App\Models\MobileDevice), the short-lived codes the app swaps for
 * tokens after the customer signs in on app.affilstack.com, and a link
 * from each access token back to the device it was issued to so signing a
 * device out kills its tokens at once. Purely additive — every existing
 * token keeps working (mobile_device_id stays null for them).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobile_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120)->nullable();
            $table->string('platform', 16)->default('unknown'); // ios|android|web|unknown
            $table->string('app_version', 32)->nullable();
            $table->string('os_version', 32)->nullable();
            $table->string('locale', 12)->nullable();
            $table->string('push_provider', 16)->nullable(); // expo|fcm|apns
            $table->string('push_token', 255)->nullable()->index();
            $table->char('refresh_token_hash', 64)->nullable()->unique();
            $table->char('previous_refresh_token_hash', 64)->nullable()->index();
            $table->timestamp('refresh_expires_at')->nullable();
            $table->timestamp('authenticated_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'revoked_at']);
        });

        Schema::create('mobile_authorization_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->text('redirect_uri');
            $table->string('code_challenge', 128);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();
        });

        Schema::table('api_tokens', function (Blueprint $table) {
            $table->foreignId('mobile_device_id')->nullable()->after('addon_install_id')->constrained('mobile_devices')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mobile_device_id');
        });

        Schema::dropIfExists('mobile_authorization_codes');
        Schema::dropIfExists('mobile_devices');
    }
};
