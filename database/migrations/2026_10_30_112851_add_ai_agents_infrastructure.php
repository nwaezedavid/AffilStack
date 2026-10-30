<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Customer AI agents (Reply Copilot, Lead Hunter, Commission Watchdog,
 * Content Autopilot, Offer Scout, Weekly briefing) and the revenue agents
 * that work for AffilStack itself (Churn saver, Upgrade nudge).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->boolean('agents_included')->default(false)->after('monthly_lead_allowance');
            $table->boolean('agent_autopilot')->default(false)->after('agents_included');
        });

        DB::table('plans')->whereIn('slug', ['growth', 'pro', 'business', 'agency'])->update(['agents_included' => true]);
        DB::table('plans')->whereIn('slug', ['pro', 'business', 'agency'])->update(['agent_autopilot' => true]);

        Schema::table('users', function (Blueprint $table) {
            $table->json('agent_settings')->nullable();
        });

        Schema::create('agent_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('agent'); // reply_copilot, lead_hunter, commission_watchdog, content_autopilot, offer_scout, weekly_briefing, churn_saver, upgrade_nudge
            $table->string('action');
            $table->string('summary', 500);
            $table->nullableMorphs('subject');
            $table->json('meta')->nullable();
            $table->string('status')->default('done'); // done, needs_approval, approved, dismissed
            $table->timestamps();

            $table->index(['user_id', 'agent', 'created_at']);
        });

        Schema::table('crm_replies', function (Blueprint $table) {
            $table->string('copilot_intent')->nullable();
            $table->string('copilot_summary', 300)->nullable();
            $table->text('copilot_draft')->nullable();
            $table->unsignedTinyInteger('copilot_confidence')->nullable();
            $table->string('copilot_status')->nullable(); // drafted, auto_queued, sent, dismissed, skipped, no_credits
            $table->timestamp('copilot_send_at')->nullable();
            $table->timestamp('copilot_processed_at')->nullable();

            $table->index(['copilot_status', 'copilot_send_at']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_replies', function (Blueprint $table) {
            $table->dropIndex(['copilot_status', 'copilot_send_at']);
            $table->dropColumn(['copilot_intent', 'copilot_summary', 'copilot_draft', 'copilot_confidence', 'copilot_status', 'copilot_send_at', 'copilot_processed_at']);
        });

        Schema::dropIfExists('agent_activities');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('agent_settings');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['agents_included', 'agent_autopilot']);
        });
    }
};
