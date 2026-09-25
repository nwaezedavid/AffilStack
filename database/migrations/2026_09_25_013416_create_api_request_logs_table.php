<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API roadmap item #7 (per-token usage analytics) — one row per /v1/*
 * call, logged by LogApiRequest for every request that reaches a
 * resolved token, not just metered ones, so the API Access page can show
 * call volume by token even for the free endpoints. cost_cents is the
 * NET amount actually kept (0 if MeterApiUsage refunded the call), never
 * the gross charge, matching what actually left the wallet.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_request_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('api_token_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('route');
            $table->string('method', 10);
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('cost_cents')->default(0);
            $table->timestamps();

            $table->index(['api_token_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_request_logs');
    }
};
