<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Only meaningful for a seat on a "shared" (Business tier) plan — see
     * the plans.seat_mode migration. "member" (default) is draft-only,
     * same as every existing agency seat; "manager" can also publish
     * content and mark a DM sequence started. Null/ignored for an
     * "isolated"-plan seat, which stays hard-coded draft-only regardless.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('seat_role')->nullable()->after('seat_offer_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('seat_role');
        });
    }
};
