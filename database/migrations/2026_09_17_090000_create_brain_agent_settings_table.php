<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row settings table (id=1) for Brain (the Marketing Agent) —
     * see App\Models\BrainAgentSetting and Filament: AI Agents > Brain
     * Settings. Two independent connections live here, each with its own
     * verification status, same shape as PaymentGatewaySetting:
     *
     * - Anthropic (Claude) API key: used for both drafting ad copy and for
     *   the agentic Meta Ads MCP tool-use call — this is what "Brain is
     *   connected to Claude" means in practice (the admin's own Anthropic
     *   account/API key, since there is no API for creating a claude.ai
     *   web Project on someone's behalf).
     * - Meta Ads MCP server: a remote MCP server (set up by the admin,
     *   using their own Meta App credentials) that Brain attaches to its
     *   Claude calls via the Messages API's MCP connector, so Claude can
     *   actually create/manage Meta ad campaigns once approved.
     */
    public function up(): void
    {
        Schema::create('brain_agent_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_enabled')->default(false); // master switch: can Brain launch/optimize LIVE campaigns
            $table->text('credentials')->nullable(); // encrypted: anthropic_api_key, anthropic_model, meta_mcp_url, meta_mcp_token
            $table->timestamp('anthropic_verified_at')->nullable();
            $table->string('anthropic_verification_status')->nullable();
            $table->text('anthropic_verification_message')->nullable();
            $table->timestamp('meta_mcp_verified_at')->nullable();
            $table->string('meta_mcp_verification_status')->nullable();
            $table->text('meta_mcp_verification_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('brain_agent_settings');
    }
};
