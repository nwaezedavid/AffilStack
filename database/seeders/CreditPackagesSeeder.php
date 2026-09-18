<?php

namespace Database\Seeders;

use App\Models\CreditPackage;
use Illuminate\Database\Seeder;

/**
 * "Users should be able to buy more credit tokens if their monthly
 * allocation finishes (do the maths and setup the appropriate price for
 * extra tokens purchase)." The math: PlansSeeder's own comment blends the
 * whole config('credits.costs') menu to roughly $0.008/credit of actual AI
 * spend, and the plan tiers themselves already retail credits at
 * $0.057-$0.095/credit depending on tier (Starter's bundled rate is
 * priciest, Agency's cheapest — the usual "bigger commitment, better unit
 * price" curve). A top-up package needs to sit inside that same band: rich
 * enough margin to be worth selling, but not so cheap per credit that it
 * undercuts upgrading to a higher plan instead. These three land at
 * $0.095, $0.0817, and $0.0727 per credit — the same downward-sloping
 * curve as the plans, comfortably above the $0.008 cost floor, and never
 * cheaper than the Agency plan's own $0.057/credit bundled rate.
 */
class CreditPackagesSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'name' => 'Quick Top-Up',
                'description' => 'A small boost to finish out the month.',
                'credits' => 200,
                'price_cents' => 1900,
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Power Pack',
                'description' => 'The best balance of savings and size for most teams.',
                'credits' => 600,
                'price_cents' => 4900,
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Bulk Pack',
                'description' => 'Maximum credits at the lowest price per credit.',
                'credits' => 1500,
                'price_cents' => 10900,
                'is_featured' => false,
                'sort_order' => 3,
            ],
        ];

        foreach ($packages as $package) {
            CreditPackage::updateOrCreate(['name' => $package['name']], $package);
        }
    }
}
