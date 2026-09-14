<?php

namespace Database\Seeders;

use App\Models\HomepageFeature;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Starter set of homepage feature cards covering the platform's real
 * modules — admin can edit, reorder, add, or remove any of these from
 * Content > Homepage Features without a code deploy.
 */
class HomepageFeaturesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (HomepageFeature::query()->exists()) {
            return;
        }

        $features = [
            [
                'icon' => '🔍',
                'title' => 'Offer research in seconds',
                'description' => 'Give it a product name, URL, and affiliate network — get back your ideal buyer, where to find them, and the best channel to promote through.',
            ],
            [
                'icon' => '✍️',
                'title' => 'SEO blog articles, one click',
                'description' => 'A complete, ready-to-publish article built to rank and convert — no blank page, no outline to write yourself.',
            ],
            [
                'icon' => '💼',
                'title' => 'A full LinkedIn campaign',
                'description' => 'Ideal-client keywords, DM sequences with follow-up timing, scroll-stopping posts, and long-form articles — all from the same research.',
            ],
            [
                'icon' => '🎬',
                'title' => 'YouTube scripts to publish-ready metadata',
                'description' => 'A timed video script, then titles, description, tags, category, and a thumbnail prompt — the whole upload package.',
            ],
            [
                'icon' => '🎭',
                'title' => 'UGC content that sounds human',
                'description' => 'Pick an AI-suggested angle, get a script and platform-ready captions built for TikTok, Reels, and Shorts.',
            ],
            [
                'icon' => '📍',
                'title' => 'Find local customers on the map',
                'description' => 'Search any niche and location, pull real businesses from Google Maps, and drop them straight into your built-in CRM.',
            ],
            [
                'icon' => '🔗',
                'title' => 'Every link tracked, every sale attributed',
                'description' => 'Branded cloaked links with click analytics, plus an earnings tracker that matches network payouts back to the exact link that earned them.',
            ],
            [
                'icon' => '🌍',
                'title' => 'Built for every market you sell to',
                'description' => 'Regenerate any piece of content for a different country or language in one click — currency, culture, and compliance included.',
            ],
            [
                'icon' => '🤝',
                'title' => 'Earn by sharing it, too',
                'description' => 'A built-in affiliate program pays you recurring commission for every marketer you bring to the platform.',
            ],
        ];

        foreach ($features as $index => $feature) {
            HomepageFeature::create([
                ...$feature,
                'media_type' => 'none',
                'sort_order' => $index,
                'is_active' => true,
            ]);
        }
    }
}
