<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            // Content calendar (Phase 3, item 5). Only meaningful for the
            // "publishable" modules (see config/calendar.php) plus
            // linkedin_dm_sequence, which uses published_at alone (as
            // "sequence started on") to anchor its follow-up reminders —
            // everything else (research, keyword lists, angle ideas) is
            // never scheduled and just keeps the 'draft' default forever.
            $table->string('calendar_status')->default('draft')->after('status'); // draft, scheduled, published, skipped
            $table->date('scheduled_for')->nullable()->after('calendar_status');
            $table->timestamp('published_at')->nullable()->after('scheduled_for');

            $table->index(['user_id', 'calendar_status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('generations', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'calendar_status']);
            $table->dropColumn(['calendar_status', 'scheduled_for', 'published_at']);
        });
    }
};
