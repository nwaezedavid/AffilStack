<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Distinguishes an ordinary user's token (default — used by the
     * browser extension and the new dashboard "API Access" page; scoped
     * to that user's own data, exactly like the dashboard itself) from an
     * 'admin' token, which only a full admin can generate from the
     * Filament API Tokens resource and which unlocks the separate
     * /api/v1/admin/* surface — see EnsureAdminApiToken.
     */
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('type')->default('user')->after('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn('type');
        });
    }
};
