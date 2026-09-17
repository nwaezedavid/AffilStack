<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlansSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name' => 'Starter',
                'slug' => 'starter',
                'description' => 'For beginners testing their first offer.',
                'price_monthly_cents' => 2700,
                'price_yearly_cents' => 27000,
                'credits_per_month' => 150,
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
                'price_monthly_cents' => 6700,
                'price_yearly_cents' => 67000,
                'credits_per_month' => 600,
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
                'price_monthly_cents' => 12700,
                'price_yearly_cents' => 127000,
                'credits_per_month' => 1800,
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
                'price_monthly_cents' => 19700,
                'price_yearly_cents' => 197000,
                'credits_per_month' => 3000,
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
                'price_monthly_cents' => 29700,
                'price_yearly_cents' => 297000,
                'credits_per_month' => 5000,
                'active_products_limit' => 0,
                'contact_limit' => 0,
                'team_seats' => 10,
                'channels' => ['research', 'blog', 'linkedin', 'youtube', 'ugc', 'pinterest', 'google_maps', 'x', 'tiktok'],
                'is_featured' => false,
                'sort_order' => 5,
            ],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}
