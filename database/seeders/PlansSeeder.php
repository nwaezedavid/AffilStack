<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

/**
 * Item #2 of the pricing/payments audit — repriced from scratch rather than
 * left as-is. There's no full per-module AI cost ledger in this app yet
 * (see config/credits.php), so this is built from what IS known plus
 * standard estimates, not a live figure:
 *
 *   - ugc_video (40 credits) is documented at ~$0.75-$1.00 in HeyGen fees —
 *     by far the single most expensive module per credit (~$0.02/credit).
 *   - Every other module is a single text-generation AI call; at typical
 *     LLM API pricing a call sized to its credit cost runs roughly
 *     $0.01-$0.08 (e.g. blog_article's 15 credits ≈ $0.08, a 3-credit reply
 *     draft ≈ $0.01) — call it $0.003-$0.004/credit blended, well under
 *     ugc_video's rate precisely because that module was already priced as
 *     "a healthy multiple" of its own real cost.
 *   - Blending the full config('credits.costs') menu on that basis lands
 *     around $0.008/credit of actual AI spend at typical usage.
 *
 * Old pricing already ran 85-95%+ gross margin at that blended cost — the
 * bottleneck was never margin, it was conversion friction at the entry
 * price point. This repricing lowers Starter (cheaper first purchase =
 * more of them) while capturing more from Pro/Business/Agency, where team
 * size and unlimited products/contacts mean the value delivered is highest
 * and price sensitivity is lowest — net more total profit, not just a
 * different split of the same money. Every tier still clears an ~85%+
 * margin at the estimate above (Starter: ~$1.60 cost vs $19 price ≈ 91.6%;
 * Agency: ~$56 cost vs $399 price ≈ 85.9%).
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
