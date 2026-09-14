<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table (id=1) for "Continue with Google" — see
     * App\Models\GoogleOauthSetting and Filament: Site > Google Login.
     */
    public function up(): void
    {
        Schema::create('google_oauth_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('google_oauth_settings');
    }
};
