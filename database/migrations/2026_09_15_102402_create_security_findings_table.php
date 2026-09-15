<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tom's (the Security Agent's) daily scan findings. `fingerprint` dedups
     * a still-open (or scheduled) issue across repeated daily scans instead
     * of piling up duplicates — see SecurityScanService::persist(). It is
     * NOT globally unique: once a finding is dismissed or fixed, the same
     * fingerprint reappearing later is a legitimately new finding (fresh
     * evidence, fresh AI explanation, its own row) rather than a reopen of
     * the old one.
     */
    public function up(): void
    {
        Schema::create('security_findings', function (Blueprint $table) {
            $table->id();
            $table->string('fingerprint')->index();
            $table->string('category'); // dependency, config, auth
            $table->string('title');
            $table->string('severity'); // low, medium, high, critical
            $table->json('evidence')->nullable();
            $table->text('ai_summary')->nullable();
            $table->text('ai_suggested_fix')->nullable();
            $table->json('fix_action')->nullable(); // {type, params} — see SecurityFixExecutor
            $table->string('status')->default('open'); // open, scheduled, fixed, dismissed
            $table->foreignId('agent_task_id')->nullable()->constrained('agent_tasks')->nullOnDelete();
            $table->timestamp('detected_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_findings');
    }
};
