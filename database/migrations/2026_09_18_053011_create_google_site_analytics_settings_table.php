<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('google_site_analytics_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            // OAuth tokens (access/refresh) plus every provisioned resource
            // id — encrypted the same way GoogleOauthSetting/EmailConnection
            // store credentials, via the model's credential() accessor.
            $table->text('credentials')->nullable();
            $table->string('connected_email')->nullable();
            $table->timestamp('connected_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('verification_message')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('google_site_analytics_settings');
    }
};
