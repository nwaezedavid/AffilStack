<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Shared across every AI agent (Tom/Sam/Brain/Tony): whenever an agent
     * needs to do something that touches the live platform — a security
     * fix, a codebase change — it creates one of these instead of acting
     * directly. Only a super-admin can move one from pending to scheduled.
     */
    public function up(): void
    {
        Schema::create('agent_tasks', function (Blueprint $table) {
            $table->id();
            $table->string('agent'); // security, support, marketing, creative
            $table->string('type'); // e.g. security_fix, codebase_change
            $table->string('title');
            $table->text('summary')->nullable();
            $table->json('payload')->nullable();
            $table->string('risk_level')->nullable(); // low, medium, high, critical
            $table->string('status')->default('pending'); // pending, scheduled, declined, running, completed, failed, rolled_back
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('scheduled_until')->nullable();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->text('result')->nullable();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
            $table->index(['agent', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_tasks');
    }
};
