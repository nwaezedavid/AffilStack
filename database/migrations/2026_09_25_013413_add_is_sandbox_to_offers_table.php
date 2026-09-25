<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * API roadmap item #5 (sandbox mode) — a sandbox-token-created offer is
 * flagged here so OffersController can keep sandbox and live data
 * mutually invisible, exactly like Stripe test-mode/live-mode objects.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->boolean('is_sandbox')->default(false)->after('user_id');
            $table->index(['user_id', 'is_sandbox']);
        });
    }

    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'is_sandbox']);
            $table->dropColumn('is_sandbox');
        });
    }
};
