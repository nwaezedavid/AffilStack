<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * "Birthday, holiday and tips emails" — the one opt-out covering every
     * lifecycle email (birthday, festive season, re-engagement). On by
     * default; toggled from Profile > Notifications or a one-click signed
     * unsubscribe link in any of those emails. last_active_at gains an
     * index because the admin Users filters and the re-engagement campaign
     * both range-scan it.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_emails_opt_in')->default(true)->after('notify_email_on_completion');
            $table->index('last_active_at');
        });

        // last_active_at existed before but nothing ever wrote it — seed it
        // from the database session store (when in use) so the admin's
        // first look at active/inactive users isn't "everyone: never".
        if (Schema::hasTable('sessions')) {
            DB::table('sessions')
                ->whereNotNull('user_id')
                ->selectRaw('user_id, MAX(last_activity) as last_activity')
                ->groupBy('user_id')
                ->get()
                ->each(fn ($row) => DB::table('users')
                    ->where('id', $row->user_id)
                    ->whereNull('last_active_at')
                    ->update(['last_active_at' => date('Y-m-d H:i:s', (int) $row->last_activity)]));
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['last_active_at']);
            $table->dropColumn('marketing_emails_opt_in');
        });
    }
};
