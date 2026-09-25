<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API roadmap item #1 — lets a client safely retry a POST /offers or
 * POST /crm-contacts call that timed out without risking a duplicate
 * record. Scoped by (user_id, key) alone, not also the route: an
 * Idempotency-Key is defined per caller, exactly like Stripe's own
 * semantics — reusing one on a different route/body is a conflict (see
 * EnsureIdempotency), not a second independent slot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->string('route');
            $table->string('request_hash', 64);
            $table->unsignedSmallInteger('response_status');
            $table->longText('response_body');
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
