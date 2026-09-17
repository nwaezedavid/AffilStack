<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit gap #7: the general-purpose API (task #6, /v1/*) was pull-only
     * — nothing ever pushed to the customer. A user-configurable endpoint,
     * one row per URL they've registered, subscribed to whichever event
     * types they choose from config('webhooks.events'). See
     * App\Services\Webhooks\WebhookDispatcher and WebhookDelivery.
     */
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('url');
            // Encrypted at rest, but — unlike ApiToken's bearer secret —
            // shown back to the user indefinitely: they need it every time
            // to verify a delivery's X-AffilStack-Signature header, not
            // just once at creation.
            $table->text('secret');
            $table->json('events');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
