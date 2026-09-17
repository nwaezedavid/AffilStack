<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Audit gap #4: self-service account deletion (soft-delete + 30-day
     * grace period, per the chosen scope). Requesting deletion sets
     * deleted_at immediately — which, via Eloquent's global SoftDeletes
     * scope, makes the account invisible to every normal User:: lookup the
     * app makes, including Fortify's login query, without touching any of
     * them individually. A daily command (see PurgeDeletedAccounts)
     * force-deletes anyone still soft-deleted 30 days later; an admin can
     * restore before then from the Users resource.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
