<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Single-row (id=1) admin configuration for the built-in GitHub sync
     * feature — see Filament: System > GitHub Sync and
     * App\Services\GitHub\GitHubSyncService. Same shape as
     * partner_stack_settings/heygen_settings: `credentials` holds only the
     * personal access token, encrypted at rest; repo_owner/repo_name/branch
     * are not secrets, so they stay plain columns. last_sync_* denormalizes
     * the most recent run onto this row (read by the Connections Health
     * aggregator and this page's own status badge) — the full run-by-run
     * history lives in github_sync_runs instead.
     */
    public function up(): void
    {
        Schema::create('github_sync_settings', function (Blueprint $table) {
            $table->id();
            $table->string('repo_owner')->nullable();
            $table->string('repo_name')->nullable();
            $table->string('branch')->default('main');
            $table->boolean('is_enabled')->default(false);
            $table->text('credentials')->nullable(); // encrypted:array cast stores ciphertext, not JSON — a json column rejects it on MariaDB/MySQL
            $table->string('last_sync_status')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->string('last_sync_commit_sha')->nullable();
            $table->text('last_sync_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('github_sync_settings');
    }
};
