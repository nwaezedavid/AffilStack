<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Inbound replies from CRM contacts, detected over IMAP or the Gmail API
 * and matched back to the send they answer.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_replies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_email_send_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_campaign_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('crm_campaign_recipient_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 10); // imap, gmail
            $table->string('external_id')->nullable();
            $table->string('message_id')->nullable();
            $table->string('in_reply_to')->nullable();
            $table->text('references')->nullable();
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->string('subject')->nullable();
            $table->longText('body_text')->nullable();
            $table->text('body_clean')->nullable();
            $table->string('matched_by', 20); // message_id, thread, sender
            $table->timestamp('received_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'message_id']);
            $table->index(['user_id', 'read_at']);
            $table->index(['crm_contact_id', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_replies');
    }
};
