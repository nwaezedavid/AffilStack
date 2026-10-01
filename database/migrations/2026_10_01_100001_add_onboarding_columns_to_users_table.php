<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The dashboard's welcome tour: finished (completed_at) or skipped
 * (dismissed_at). Both null means the tour starts on the user's next
 * dashboard visit — including for accounts that existed before the tour.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('onboarding_completed_at')->nullable();
            $table->timestamp('onboarding_dismissed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['onboarding_completed_at', 'onboarding_dismissed_at']);
        });
    }
};
