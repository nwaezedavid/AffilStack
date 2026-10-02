<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Admin-editable lifecycle email campaigns — birthday, every festive
     * season, and each re-engagement step — plus the per-user send log
     * that guarantees each one goes out at most once per occurrence (see
     * LifecycleEmailLog::$occurrence). Presets are installed by the next
     * migration so production gets them on a plain `php artisan migrate`.
     */
    public function up(): void
    {
        Schema::create('lifecycle_emails', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('type', 20)->index();
            $table->string('name');
            $table->string('subject');
            $table->string('preheader')->nullable();
            $table->string('heading');
            $table->text('body');
            $table->string('cta_label')->nullable();
            $table->string('cta_url')->nullable();
            $table->string('hero_image_path')->nullable();
            $table->text('image_hint')->nullable();
            $table->string('theme_color', 7)->default('#2452D9');
            $table->boolean('enabled')->default(true);
            $table->string('category', 20)->nullable();
            $table->string('religion', 40)->nullable();
            $table->json('countries')->nullable();
            $table->json('date_rule')->nullable();
            $table->json('date_overrides')->nullable();
            $table->unsignedSmallInteger('days_before')->default(0);
            $table->unsignedSmallInteger('inactive_days')->nullable();
            $table->unsignedInteger('bonus_credits')->default(0);
            $table->text('admin_notes')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('lifecycle_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lifecycle_email_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->string('occurrence', 40);
            $table->date('local_date')->nullable();
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('bonus_credits_granted')->default(0);
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lifecycle_email_id', 'occurrence'], 'lifecycle_logs_once_per_occurrence');
            $table->index(['user_id', 'type', 'local_date']);
        });

        Schema::create('user_activity_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('date');

            $table->unique(['user_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_activity_days');
        Schema::dropIfExists('lifecycle_email_logs');
        Schema::dropIfExists('lifecycle_emails');
    }
};
