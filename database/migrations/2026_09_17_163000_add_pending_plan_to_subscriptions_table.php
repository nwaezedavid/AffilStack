<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Self-service plan upgrade/downgrade (audit item #2). An upgrade takes
 * effect immediately (see PlanChangeService::prorationCreditCents()), but a
 * downgrade is scheduled here instead — the user keeps everything their
 * current, already-paid-for plan gives them until the period actually
 * ends, rather than losing access to something they paid for.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->foreignId('pending_plan_id')->nullable()->after('plan_id')->constrained('plans')->nullOnDelete();
            $table->string('pending_billing_cycle')->nullable()->after('pending_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pending_plan_id');
            $table->dropColumn('pending_billing_cycle');
        });
    }
};
