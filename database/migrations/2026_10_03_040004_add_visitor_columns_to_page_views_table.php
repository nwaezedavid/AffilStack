<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agent 04: extends the existing first-party page-view beacon instead of
     * adding a second page-view table. A view recorded WITH analytics
     * consent links to its visitor/session and stores scroll depth; one
     * recorded without consent is an anonymous aggregate count — no session
     * id, no user, no visitor — which is why session_id becomes nullable.
     */
    public function up(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->string('session_id', 100)->nullable()->change();
            $table->foreignId('visitor_id')->nullable()->after('session_id')->constrained('analytics_visitors')->nullOnDelete();
            $table->foreignId('analytics_session_id')->nullable()->after('visitor_id')->constrained('analytics_sessions')->nullOnDelete();
            $table->unsignedTinyInteger('scroll_depth')->nullable()->after('duration_seconds');
        });
    }

    public function down(): void
    {
        Schema::table('page_views', function (Blueprint $table) {
            $table->dropConstrainedForeignId('analytics_session_id');
            $table->dropConstrainedForeignId('visitor_id');
            $table->dropColumn('scroll_depth');
        });
    }
};
