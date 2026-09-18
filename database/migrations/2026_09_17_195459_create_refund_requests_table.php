<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Audit item #8 — an audit trail of every refund request and its outcome.
 * Deliberately NOT an approval queue: eligibility (see
 * RefundEligibilityService) is a strict, automatic 48-hour/zero-usage rule
 * with no admin discretion, so every row here already reflects a final,
 * system-decided outcome by the time it's written — this table exists so
 * support can see what happened and why, not so an admin can act on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('refund_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status'); // refunded, ineligible
            $table->string('reason'); // the eligibility outcome message shown to the user
            $table->string('gateway')->nullable();
            $table->string('gateway_reference')->nullable();
            $table->integer('amount_cents')->nullable();
            $table->string('currency')->nullable();
            $table->timestamp('requested_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refund_requests');
    }
};
