<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per GitHub sync attempt (manual "Sync now" click or the daily
     * `github:sync` schedule) — see App\Services\GitHub\GitHubSyncService
     * and App\Models\GitHubSyncRun. Deliberately separate from
     * scheduled_task_runs: that table only ever records whether the
     * artisan command itself executed, not what a given sync actually did
     * (nothing to commit vs. pushed a new commit vs. refused over a
     * detected secret file) — this is the admin-facing sync history shown
     * on the GitHub Sync settings page.
     */
    public function up(): void
    {
        Schema::create('github_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->string('status');
            $table->string('trigger');
            $table->text('message')->nullable();
            $table->string('commit_sha')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_sync_runs');
    }
};
