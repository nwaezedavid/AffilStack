<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: a visit — a run of page views/events from one visitor with
     * no gap longer than config('analytics.session_timeout_minutes'). Each
     * session carries its own (last-touch) attribution so "which campaign
     * brought this visit" is answerable per visit, not just per visitor.
     */
    public function up(): void
    {
        Schema::create('analytics_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->constrained('analytics_visitors')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('last_activity_at');
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->unsignedInteger('engaged_seconds')->default(0);
            $table->unsignedSmallInteger('pageviews')->default(0);
            $table->unsignedSmallInteger('events')->default(0);
            $table->string('landing_path', 200)->nullable();
            $table->string('exit_path', 200)->nullable();
            $table->string('channel', 40)->nullable()->index();
            $table->string('source', 100)->nullable();
            $table->string('medium', 100)->nullable();
            $table->string('campaign', 150)->nullable();
            $table->string('term', 150)->nullable();
            $table->string('content', 150)->nullable();
            $table->string('referrer_host', 255)->nullable();
            $table->string('click_id_type', 10)->nullable();
            $table->string('device_type', 20)->nullable();
            $table->string('browser', 40)->nullable();
            $table->string('os', 40)->nullable();
            $table->string('language', 20)->nullable();
            $table->string('country', 2)->nullable();
            $table->string('city', 100)->nullable();

            $table->index(['visitor_id', 'last_activity_at']);
            $table->index(['user_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_sessions');
    }
};
