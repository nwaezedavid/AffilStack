<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: named behaviour events — automatic (CTA clicks, pricing
     * viewed, checkout started…), server-side (signup completed, research
     * started, content generated…) and custom ones from
     * window.affilstackTrack(). visitor_id/session_id/user_id are all null
     * for an anonymous aggregate count recorded without analytics consent.
     * dedupe_key stops a server-side event (e.g. one payment) from being
     * counted twice when a webhook and the browser callback both arrive.
     */
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visitor_id')->nullable()->constrained('analytics_visitors')->cascadeOnDelete();
            $table->foreignId('session_id')->nullable()->constrained('analytics_sessions')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('category', 20)->default('custom');
            $table->string('path', 200)->nullable();
            $table->json('properties')->nullable();
            $table->integer('value_cents')->nullable();
            $table->string('currency', 3)->nullable();
            $table->string('dedupe_key', 100)->nullable()->unique();
            $table->timestamp('created_at')->useCurrent()->index();

            $table->index(['name', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index(['visitor_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
