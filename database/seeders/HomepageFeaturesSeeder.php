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

    /**
     * Cards for features 4, 5 and 7, also added to existing sites by the
     * 2026_10_22_202615 migration.
     *
     * @var array<int, array{icon: string, title: string, default_image: string, link_url: string, description: string}>
     */
    public const NEW_FEATURE_CARDS = [
        [
            'icon' => 'arrows-right-left',
            'title' => 'Your affiliate networks, connected',
            'default_image' => 'affiliate-networks.webp',
            'link_url' => '/features/affiliate-networks',
            'description' => 'Connect Impact, PartnerStack, Awin, CJ, ClickBank or Digistore24 — turn joined programs into products and import your commissions every day.',
        ],
        [
            'icon' => 'globe-alt',
            'title' => 'Straight to your WordPress site',
            'default_image' => 'wordpress-publishing.webp',
            'link_url' => '/features/wordpress-publishing',
            'description' => 'The free AffilStack Connector plugin delivers every article as a draft, with images, SEO fields, FAQ schema and tracked links in place.',
        ],
        [
            'icon' => 'puzzle-piece',
            'title' => 'An add-on marketplace',
            'default_image' => 'add-on-marketplace.webp',
            'link_url' => '/features/add-on-marketplace',
            'description' => 'Connected apps, template packs and automation recipes from independent developers, each one reviewed before it goes live.',
        ],
    ];

    public function run(): void
    {
        if (HomepageFeature::query()->exists()) {
            return;
        }

        $features = [
            [
                'icon' => 'magnifying-glass',
                'title' => 'Offer research in seconds',
                'default_image' => 'offer-research.webp',
                'link_url' => '/features/offer-research',
                'description' => 'Give it a product name, URL, and affiliate network — get back your ideal buyer, where to find them, and the best channel to promote through.',
            ],
            [
                'icon' => 'pencil-square',
                'title' => 'SEO blog articles, one click',
                'default_image' => 'blog-article.webp',
                'link_url' => '/features/blog-articles',
                'description' => 'A complete, ready-to-publish article built to rank and convert — no blank page, no outline to write yourself.',
            ],
            [
                'icon' => 'user-group',
                'title' => 'A full LinkedIn campaign',
                'default_image' => 'linkedin-prospecting.webp',
                'link_url' => '/features/linkedin-prospecting',
                'description' => 'Ideal-client keywords, DM sequences with follow-up timing, scroll-stopping posts, and long-form articles — all from the same research.',
            ],
            [
                'icon' => 'video-camera',
                'title' => 'YouTube scripts to publish-ready metadata',
                'default_image' => 'youtube-script.webp',
                'link_url' => '/features/youtube-scripts',
                'description' => 'A timed video script, then titles, description, tags, category, and a thumbnail prompt — the whole upload package.',
            ],
            [
                'icon' => 'sparkles',
                'title' => 'UGC content that sounds human',
                'default_image' => 'ugc-video.webp',
                'link_url' => '/features/ugc-video',
                'description' => 'Pick an AI-suggested angle, get a script and platform-ready captions built for TikTok, Reels, and Shorts.',
            ],
            [
                'icon' => 'map-pin',
                'title' => 'Find local customers on the map',
                'default_image' => 'local-leads.webp',
                'link_url' => '/features/google-maps-lead-finder',
                'description' => 'Search any niche and location, pull real businesses from Google Maps, and drop them straight into your built-in CRM.',
            ],
            [
                'icon' => 'link',
                'title' => 'Every link tracked, every sale attributed',
                'default_image' => 'tracked-links.webp',
                'link_url' => '/features/link-tracking',
                'description' => 'Branded cloaked links with click analytics, plus an earnings tracker that matches network payouts back to the exact link that earned them.',
            ],
            [
                'icon' => 'globe-alt',
                'title' => 'Built for every market you sell to',
                'default_image' => 'localization.webp',
                'link_url' => '/features/localization',
                'description' => 'Regenerate any piece of content for a different country or language in one click — currency, culture, and compliance included.',
            ],
            ...self::NEW_FEATURE_CARDS,
            [
                'icon' => 'banknotes',
                'title' => 'Earn by sharing it, too',
                'default_image' => 'marketing-kit.webp',
                'link_url' => '/affiliate',
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
