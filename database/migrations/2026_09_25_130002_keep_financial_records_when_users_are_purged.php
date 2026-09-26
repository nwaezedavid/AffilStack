<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Deleted accounts are hard-deleted 30 days later (users:purge-deleted), and
 * these foreign keys cascaded that delete into records the business must
 * keep: every payment the customer ever made (revenue history, tax, dispute
 * evidence), refund records, affiliate payouts, and — through referrals —
 * the commission ledger of whoever referred them. They now keep the row and
 * just lose the link to the deleted user.
 */
return new class extends Migration
{
    /** @var array<string, string> table => column */
    private array $columns = [
        'payment_transactions' => 'user_id',
        'refund_requests' => 'user_id',
        'referral_payouts' => 'user_id',
        'referrals' => 'referred_user_id',
    ];

    public function up(): void
    {
        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->unsignedBigInteger($column)->nullable()->change();
                $blueprint->foreign($column)->references('id')->on('users')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ($this->columns as $table => $column) {
            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->dropForeign([$column]);
            });

            Schema::table($table, function (Blueprint $blueprint) use ($column) {
                $blueprint->foreign($column)->references('id')->on('users')->cascadeOnDelete();
            });
        }
    }
};
