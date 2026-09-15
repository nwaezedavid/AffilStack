<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sam (the Support Agent) draft new canned replies from recurring
     * patterns in resolved tickets — see SamAgentService::suggestTemplates().
     * A drafted template lands here as `status=suggested`, `source=ai_suggested`
     * and never reaches the reply picker (MessagesRelationManager only offers
     * `status=active` ones) until a staff/admin reviews and activates it.
     * `usage_count`/`last_used_at` are Sam's own effectiveness signal, bumped
     * whenever staff pick a template while replying to a ticket.
     */
    public function up(): void
    {
        Schema::table('canned_replies', function (Blueprint $table) {
            $table->string('status')->default('active')->after('body'); // active, suggested, archived
            $table->string('source')->default('manual')->after('status'); // manual, ai_suggested
            $table->unsignedInteger('usage_count')->default(0)->after('source');
            $table->timestamp('last_used_at')->nullable()->after('usage_count');
            $table->text('ai_rationale')->nullable()->after('last_used_at');
        });
    }

    public function down(): void
    {
        Schema::table('canned_replies', function (Blueprint $table) {
            $table->dropColumn(['status', 'source', 'usage_count', 'last_used_at', 'ai_rationale']);
        });
    }
};
