<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Item #2 of the pricing/payments audit — carries the PlansSeeder repricing
 * into any database that was already seeded with the old numbers, rather
 * than only affecting a fresh install. Guarded by slug + the exact old
 * price so a plan an admin has since hand-edited in Filament is left
 * alone — this only touches rows that still match what PlansSeeder
 * originally created. See PlansSeeder for the actual cost/margin reasoning.
 */
return new class extends Migration
{
    public function up(): void
    {
        $reprices = [
            'starter' => ['old_monthly' => 2700, 'new_monthly' => 1900, 'new_credits' => 200],
            'growth' => ['old_monthly' => 6700, 'new_monthly' => 5900, 'new_credits' => 700],
            'pro' => ['old_monthly' => 12700, 'new_monthly' => 14900, 'new_credits' => 2200],
            'business' => ['old_monthly' => 19700, 'new_monthly' => 24900, 'new_credits' => 4000],
            'agency' => ['old_monthly' => 29700, 'new_monthly' => 39900, 'new_credits' => 7000],
        ];

        foreach ($reprices as $slug => $reprice) {
            DB::table('plans')
                ->where('slug', $slug)
                ->where('price_monthly_cents', $reprice['old_monthly'])
                ->update([
                    'price_monthly_cents' => $reprice['new_monthly'],
                    'price_yearly_cents' => Plan::yearlyPriceCentsFor($reprice['new_monthly']),
                    'credits_per_month' => $reprice['new_credits'],
                ]);
        }
    }

    public function down(): void
    {
        $reprices = [
            'starter' => ['old_monthly' => 2700, 'old_credits' => 150],
            'growth' => ['old_monthly' => 6700, 'old_credits' => 600],
            'pro' => ['old_monthly' => 12700, 'old_credits' => 1800],
            'business' => ['old_monthly' => 19700, 'old_credits' => 3000],
            'agency' => ['old_monthly' => 29700, 'old_credits' => 5000],
        ];

        foreach ($reprices as $slug => $reprice) {
            DB::table('plans')
                ->where('slug', $slug)
                ->update([
                    'price_monthly_cents' => $reprice['old_monthly'],
                    'price_yearly_cents' => $reprice['old_monthly'] * 10,
                    'credits_per_month' => $reprice['old_credits'],
                ]);
        }
    }
};
