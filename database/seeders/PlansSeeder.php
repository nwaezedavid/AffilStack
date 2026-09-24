<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Item #2 of the pricing/payments audit — repriced from scratch rather than
 * left as-is. There's no full per-module AI cost ledger in this app yet
 * (see config/credits.php), so this is built from what IS known plus
 * standard estimates, not a live figure. Revisited and corrected once more
 * (see the platform-wide AI-cost-vs-pricing review this docblock was last
 * updated alongside): the original version of this estimate assumed
 * ugc_video cost ~$0.75-$1.00 in HeyGen fees, which was wrong — verified
 * against HeyGen's real API pricing ($0.05/second) and UgcService's own
 * video_script prompt target (~45-60 seconds spoken), the real cost is
 * $2.25-$3.00. config/credits.php's ugc_video price was corrected from 40
 * to 100 credits to match, which is what restores the numbers below rather
 * than requiring a change to the plan prices or credit allotments
 * themselves:
 *
 *   - ugc_video (100 credits, corrected) costs $2.25-$3.00 in HeyGen fees —
 *     by far the single most expensive module per credit, but now priced
 *     to match (~$0.0225-$0.03/credit, roughly what this module was always
 *     intended to cost before the underlying HeyGen assumption was wrong).
 *   - Every other module is a single text-generation AI call; at gpt-4o-mini's
 *     real, verified pricing ($0.15/$0.60 per 1M input/output tokens) even a
 *     deliberately generous worst-case estimate for any single call comes to
 *     roughly $0.0015 — under $0.0002/credit blended across the whole
 *     non-video menu, two orders of magnitude cheaper than ugc_video.
 *   - Blending the full config('credits.costs') menu at a realistic mix
 *     (most users spend a minority of credits on video) lands around
 *     84-97% gross margin at up to 30% of credits spent on ugc_video, and
 *     ~100% margin for a user who never touches it — comfortably inside the
 *     85-95%+ this pricing was designed around. A single user spending
 *     their *entire* monthly allowance on nothing but ugc_video (the
 *     extreme, unthrottled worst case) still holds 47-76% margin depending
 *     on plan and script length, instead of running an outright loss on
 *     Pro/Business/Agency the way the old 40-credit price did.
 *
 * The bottleneck was never margin, it was conversion friction at the entry
 * price point. This repricing lowers Starter (cheaper first purchase =
 * more of them) while capturing more from Pro/Business/Agency, where team
 * size and unlimited products/contacts mean the value delivered is highest
 * and price sensitivity is lowest — net more total profit, not just a
 * different split of the same money.
 *
 * Yearly price is never typed independently — see Plan::yearlyPriceCentsFor().
 */
class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For beginners testing their first offer.',
                'price_monthly_cents' => 1900,
                'credits_per_month' => 200,
                'active_products_limit' => 1,
                'contact_limit' => 500,
                'team_seats' => 1,
                'channels' => ['research', 'blog', 'linkedin'],
                'is_featured' => false,
                'sort_order' => 1,
            ],
            [
                'name' => 'Growth',
                'slug' => 'growth',
                'description' => 'For affiliates running multiple offers across every channel.',
                'price_monthly_cents' => 5900,
                'credits_per_month' => 700,
                'active_products_limit' => 5,
                'contact_limit' => 5000,
                'team_seats' => 1,
                'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok'],
                'is_featured' => true,
                'sort_order' => 2,
            ],
            [
                'name' => 'Pro',
                'slug' => 'pro',
                'description' => 'For full-time affiliates and small teams.',
                'price_monthly_cents' => 14900,
                'credits_per_month' => 2200,
                'active_products_limit' => 0,
                'contact_limit' => 0,
                'team_seats' => 3,
                'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok'],
                'is_featured' => false,
                'sort_order' => 3,
            ],
            [
                'name' => 'Business',
                'slug' => 'business',
                'description' => 'For an in-house team collaborating on one brand — every seat shares the whole offer list.',
                'price_monthly_cents' => 24900,
                'credits_per_month' => 4000,
                'active_products_limit' => 0,
                'contact_limit' => 0,
                'team_seats' => 5,
                'seat_mode' => 'shared',
                'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok'],
                'is_featured' => false,
                'sort_order' => 4,
            ],
            [
                'name' => 'Agency',
                'slug' => 'agency',
                'description' => 'For agencies managing client affiliate accounts.',
                'price_monthly_cents' => 39900,
                'credits_per_month' => 7000,
                'active_products_limit' => 0,
                'contact_limit' => 0,
                'team_seats' => 10,
                'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok'],
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            $plan['price_yearly_cents'] = Plan::yearlyPriceCentsFor($plan['price_monthly_cents']);

            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
