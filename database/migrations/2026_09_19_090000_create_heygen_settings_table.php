<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row (id=1) admin configuration for the UGC video overhaul: one
     * HeyGen API key, connected and verified from Content > UGC Video
     * Settings, that every user's video generation runs through — users
     * spend AffilStack credits, never see or need a HeyGen account of
     * their own. Credentials encrypted at rest, same pattern as
     * PaymentGatewaySetting/BrainAgentSetting.
     */
    public function up(): void
    {
        Schema::create('heygen_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array cast stores ciphertext, not JSON — a json column rejects it on MariaDB/MySQL
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('verification_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heygen_settings');
    }
};
