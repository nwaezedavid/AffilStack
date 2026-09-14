<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per actual email sent to a CRM contact from the platform
     * (CrmEmailService) — usually one step of an AI-generated nurture
     * sequence, so this is also the record of what was really sent (the
     * cloaked body, with its affiliate link already rewritten to route
     * through this row's own tracking_token) versus the raw generated
     * draft still on generations.output_meta.
     *
     * link_destination is the real short link (e.g. /go/{code}) this
     * send's click-tracking redirect (see CrmEmailTrackingController)
     * sends the recipient on to — resolved and stored server-side at send
     * time, never taken from the request, so the public /e/c/{token}
     * route can never be turned into an open redirect.
     */
    public function up(): void
    {
        Schema::create('crm_email_sends', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('crm_contact_id')->constrained()->cascadeOnDelete();
            $table->foreignId('generation_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('sequence_step')->nullable();
            $table->string('subject');
            $table->text('body');
            $table->string('link_destination')->nullable();
            $table->string('status')->default('queued'); // queued, sent, failed
            $table->text('error_message')->nullable();
            $table->string('tracking_token')->unique();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->timestamp('first_clicked_at')->nullable();
            $table->unsignedInteger('click_count')->default(0);
            $table->timestamps();

            $table->index(['crm_contact_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_email_sends');
    }
};
