<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #3: single-row (id=1) admin configuration for "Connect LinkedIn"
     * — a self-serve "Sign In with LinkedIn using OpenID Connect" app
     * (scope: openid profile email only, no posting scope — see
     * App\Services\Social\LinkedInOAuthService's docblock for why this
     * never requests write access). Same shape/pattern as
     * GoogleOauthSetting.
     */
    public function up(): void
    {
        Schema::create('linkedin_oauth_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array cast stores ciphertext, not JSON — a json column rejects it on MariaDB/MySQL
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_oauth_settings');
    }
};
