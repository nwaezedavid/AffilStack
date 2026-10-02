<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Email campaigns sent to a CRM list (one product's prospects), plus one
 * recipient row per (campaign, contact) — the history of who got what,
 * which also makes "send to the N contacts added since" and autopilot
 * idempotent (the unique index).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('offer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('subject')->default('');
            $table->text('body')->nullable();
            $table->string('status', 20)->default('draft'); // draft, approved, sending, sent, paused
            $table->boolean('autopilot')->default(false);
            $table->boolean('personalize_opening')->default(false);
            $table->json('follow_up_steps')->nullable(); // [{delay_days, subject, body}]
            $table->string('tone', 40)->nullable();
            $table->unsignedInteger('credits_spent')->default(0);
            $table->timestamp('generated_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->string('paused_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['crm_list_id', 'autopilot']);
        });

        Schema::create('crm_campaign_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20)->default('queued'); // queued, sent, failed, skipped, replied, unsubscribed
            $table->string('source', 20)->default('launch'); // launch, new_contacts, autopilot
            $table->unsignedTinyInteger('steps_sent')->default(0);
            $table->timestamp('next_step_due_at')->nullable();
            $table->timestamp('last_sent_at')->nullable();
            $table->timestamp('replied_at')->nullable();
            $table->string('opening_line', 500)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(['crm_campaign_id', 'crm_contact_id']);
            $table->index(['status', 'next_step_due_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_recipients');
        Schema::dropIfExists('crm_campaigns');
    }
};
