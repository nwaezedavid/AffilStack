<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Gemini as a third text engine, plus which engine is tried second when
 * the main one fails (the remaining one is tried last).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_engine_settings', function (Blueprint $table) {
            $table->string('second_engine')->nullable()->after('text_engine');
            $table->timestamp('gemini_verified_at')->nullable();
            $table->string('gemini_verification_status')->nullable();
            $table->text('gemini_verification_message')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ai_engine_settings', function (Blueprint $table) {
            $table->dropColumn(['second_engine', 'gemini_verified_at', 'gemini_verification_status', 'gemini_verification_message']);
        });
    }
};
