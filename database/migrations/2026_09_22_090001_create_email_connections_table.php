<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Task #1: a user's own connected email sender for CRM nurture
     * sends — Gmail (OAuth) or their domain's SMTP — kept deliberately
     * separate from AffilStack's own transactional mail (config('mail'))
     * to protect the platform's shared sending domain's reputation, per
     * the backlog's own stated reasoning. One row per user (never
     * team-shared — CRM contacts aren't either, see User::crmContacts()),
     * and CrmEmailService switches to sending exclusively through this
     * once it exists — see that class for the routing logic.
     */
    public function up(): void
    {
        Schema::create('email_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('provider'); // 'gmail' | 'smtp'
            $table->text('credentials')->nullable(); // encrypted:array cast stores ciphertext, not JSON — a json column rejects it on MariaDB/MySQL // encrypted at rest — see EmailConnection casts
            $table->string('connected_email')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_status')->nullable();
            $table->text('verification_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_connections');
    }
};
