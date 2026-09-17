<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The Business/Team tier design: how a plan's seats (Plan::team_seats,
     * agency/team seats item 10) relate to the account's offers.
     * "isolated" (every existing plan, unchanged) is the original agency
     * model — a seat is scoped to exactly one offer, draft-only. "shared"
     * (the new Business plan) is a real internal team — every seat sees
     * and works every offer under the account, and a seat's own
     * User::seat_role decides whether it can also publish. See
     * User::onSharedTeamPlan()/hasSharedTeamAccess()/isTeamManager().
     */
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('seat_mode')->default('isolated')->after('team_seats');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn('seat_mode');
        });
    }
};
