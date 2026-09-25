<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API roadmap items #2 (scoped read-only tokens) and #5 (sandbox mode).
 * Both default to the pre-existing behavior (full access, live data) so
 * every token minted before this migration keeps working unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->string('scope')->default('full')->after('type'); // full|read_only
            $table->boolean('is_sandbox')->default(false)->after('scope');
        });
    }

    public function down(): void
    {
        Schema::table('api_tokens', function (Blueprint $table) {
            $table->dropColumn(['scope', 'is_sandbox']);
        });
    }
};
