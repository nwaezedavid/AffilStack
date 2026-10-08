<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Which AI engine writes AffilStack's text content (research, emails,
     * articles, scripts…): OpenAI or Anthropic's Claude, with the other one
     * as an automatic fallback. Image generation always uses OpenAI.
     */
    public function up(): void
    {
        Schema::create('ai_engine_settings', function (Blueprint $table) {
            $table->id();
            $table->string('text_engine', 20)->default('openai');
            $table->boolean('fallback_enabled')->default(true);
            $table->text('credentials')->nullable();
            $table->timestamp('anthropic_verified_at')->nullable();
            $table->string('anthropic_verification_status', 20)->nullable();
            $table->text('anthropic_verification_message')->nullable();
            $table->timestamp('openai_verified_at')->nullable();
            $table->string('openai_verification_status', 20)->nullable();
            $table->text('openai_verification_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_engine_settings');
    }
};
