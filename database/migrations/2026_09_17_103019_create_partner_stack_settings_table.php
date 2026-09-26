<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row (id=1) admin configuration for a future PartnerStack
     * connection — deliberately connection-only for now (public/secret
     * API key, enable toggle, live verification), same shape as
     * HeyGenSetting/PaymentGatewaySetting. PartnerStack is a full
     * system-of-record for an affiliate program rather than something you
     * sync bidirectionally with an existing one, so there is no sync
     * logic yet — this exists purely so the connection can be flipped on
     * from the admin dashboard once a real integration path (parallel
     * acquisition channel vs. migration) is decided.
     */
    public function up(): void
    {
        Schema::create('partner_stack_settings', function (Blueprint $table) {
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
        Schema::dropIfExists('partner_stack_settings');
    }
};
