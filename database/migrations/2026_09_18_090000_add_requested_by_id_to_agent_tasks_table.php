<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tom's and Brain's tasks are system-generated (a scan, a scheduled
     * pass), so AgentTask never needed to track who asked for one — but
     * Tony (the Creative Agent) only ever drafts something because an
     * admin typed a brief for it, and that's worth keeping on the record
     * alongside who later approved or declined it.
     */
    public function up(): void
    {
        Schema::table('agent_tasks', function (Blueprint $table) {
            $table->foreignId('requested_by_id')->nullable()->after('payload')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('agent_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('requested_by_id');
        });
    }
};
