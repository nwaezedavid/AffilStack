<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lets a CrmEmailSend belong to a campaign (initial email or a follow-up
 * step) or be an inbox reply, and records the RFC 5322 Message-ID it went
 * out with — the key replies are matched back on (In-Reply-To/References).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_email_sends', function (Blueprint $table) {
            $table->string('kind', 20)->default('nurture')->after('generation_id'); // nurture, campaign, follow_up, reply
            $table->foreignId('crm_campaign_id')->nullable()->after('kind')->constrained()->nullOnDelete();
            $table->foreignId('crm_campaign_recipient_id')->nullable()->after('crm_campaign_id')->constrained()->nullOnDelete();
            $table->string('message_id')->nullable()->after('tracking_token');
            $table->string('in_reply_to')->nullable()->after('message_id');
            $table->string('provider_message_id')->nullable()->after('in_reply_to');
            $table->string('provider_thread_id')->nullable()->after('provider_message_id');

            $table->index('message_id');
            $table->index(['user_id', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_email_sends', function (Blueprint $table) {
            $table->dropIndex(['message_id']);
            $table->dropIndex(['user_id', 'sent_at']);
            $table->dropConstrainedForeignId('crm_campaign_recipient_id');
            $table->dropConstrainedForeignId('crm_campaign_id');
            $table->dropColumn(['kind', 'message_id', 'in_reply_to', 'provider_message_id', 'provider_thread_id']);
        });
    }
};
