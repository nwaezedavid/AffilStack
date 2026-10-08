<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Homepage feature cards get a shipped default screenshot (shown until
     * an admin uploads their own image or adds a video) and a "Learn more"
     * link to the matching feature page. The nine cards seeded by
     * HomepageFeaturesSeeder are filled in — and their emoji icons swapped
     * for the site's Heroicons — only where an admin hasn't changed them.
     */
    public function up(): void
    {
        Schema::table('homepage_features', function (Blueprint $table) {
            $table->string('default_image')->nullable()->after('image_path');
            $table->string('link_url')->nullable()->after('youtube_url');
        });

        foreach (self::SEEDED as $title => [$emoji, $icon, $image, $link]) {
            DB::table('homepage_features')->where('title', $title)->whereNull('default_image')->update(['default_image' => $image, 'link_url' => $link]);
            DB::table('homepage_features')->where('title', $title)->where('icon', $emoji)->update(['icon' => $icon]);
        }
    }

    public function down(): void
    {
        Schema::table('homepage_features', function (Blueprint $table) {
            $table->dropColumn(['default_image', 'link_url']);
        });
    }

    /**
     * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
     */
    private const SEEDED = [
        'Offer research in seconds' => ['🔍', 'magnifying-glass', 'offer-research.webp', '/features/offer-research'],
        'SEO blog articles, one click' => ['✍️', 'pencil-square', 'blog-article.webp', '/features/blog-articles'],
        'A full LinkedIn campaign' => ['💼', 'user-group', 'linkedin-prospecting.webp', '/features/linkedin-prospecting'],
        'YouTube scripts to publish-ready metadata' => ['🎬', 'video-camera', 'youtube-script.webp', '/features/youtube-scripts'],
        'UGC content that sounds human' => ['🎭', 'sparkles', 'ugc-video.webp', '/features/ugc-video'],
        'Find local customers on the map' => ['📍', 'map-pin', 'local-leads.webp', '/features/google-maps-lead-finder'],
        'Every link tracked, every sale attributed' => ['🔗', 'link', 'tracked-links.webp', '/features/link-tracking'],
        'Built for every market you sell to' => ['🌍', 'globe-alt', 'localization.webp', '/features/localization'],
        'Earn by sharing it, too' => ['🤝', 'banknotes', 'marketing-kit.webp', '/affiliate'],
    ];
};
