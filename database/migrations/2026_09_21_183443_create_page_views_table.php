<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * First-party page-view tracking (the "statistics area" request):
     * recorded client-side via a tiny beacon script (see
     * partials/analytics-beacon.blade.php + AnalyticsBeaconController) so a
     * CDN or browser cache in front of the app never causes an undercount
     * the way server-middleware-only tracking would. One row per page load;
     * duration_seconds is filled in by a second beacon call on page unload
     * and stays null if the visitor left before that ever fires.
     */
    public function up(): void
    {
        Schema::create('page_views', function (Blueprint $table) {
            $table->id();
            $table->string('path', 200)->index();
            $table->string('source', 40)->default('direct')->index();
            $table->string('referrer_host', 255)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('session_id', 100)->index();
            // An unguessable per-view token, returned to the client instead
            // of the raw id: the duration beacon authorizes its update by
            // this token rather than by matching session_id, since a
            // session can legitimately regenerate between page-load and
            // page-hide (e.g. the visitor logs in partway through the
            // visit) — matching on session_id would silently drop that
            // visit's time-on-page.
            $table->string('view_token', 64)->unique();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_views');
    }
};
