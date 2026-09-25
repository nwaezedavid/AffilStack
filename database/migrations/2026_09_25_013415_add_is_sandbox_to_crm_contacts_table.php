<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API roadmap item #5 (sandbox mode) — see the matching offers migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('user_id');
            $table->index(['user_id', 'is_sandbox']);
        });
    }

    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });
    }
};
