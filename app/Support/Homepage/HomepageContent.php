<?php

namespace App\Support\Homepage;

use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Support\SafeHtml;
use Illuminate\Support\HtmlString;

/**
 * Everything the public homepage reads that an admin can change from
 * Content → Homepage (App\Filament\Pages\HomepageEditor): which sections
 * show and in what order, plus every piece of copy, with genuine (never
 * fabricated) default copy for anything left blank.
 *
 * All values live in SiteSetting, so they're cached per key and — just as
 * importantly — a Tony (Creative Agent) branding preview, which overrides
 * SiteSetting::get() for one request (SiteSetting::withPreviewOverrides()),
 * previews homepage copy changes for free. The hero keeps its original
 * hero_* keys for exactly that reason (they're in Tony's BRANDING_KEYS).
 */
class HomepageContent
{
    /**
     * Every homepage section, in its default order.
     *
     * @var array<string, array{label: string, description: string}>
     */
    public const SECTIONS = [
        'hero' => ['label' => 'Hero', 'description' => 'Headline, buttons and the hero image or video.'],
        'logos' => ['label' => 'Brand logo marquee', 'description' => 'The scrolling "trusted by" logos (Website → Company logos). Hidden until a logo exists.'],
        'how_it_works' => ['label' => 'How it works', 'description' => 'The four-step journey: research → buyers → campaigns → earnings.'],
        'features' => ['label' => 'Feature highlights', 'description' => 'Feature cards with screenshots (Website → Homepage feature cards).'],
        'video' => ['label' => 'See AffilStack in action (video)', 'description' => 'Your product video. Hidden until a YouTube link is saved.'],
        'stats' => ['label' => 'Results / stats band', 'description' => 'Four big numbers on a navy band.'],
        'testimonials' => ['label' => 'Testimonials', 'description' => 'Shown once at least 3 testimonials are published.'],
        'pricing' => ['label' => 'Pricing teaser', 'description' => 'Up to three plans, linking to /pricing.'],
        'faq' => ['label' => 'FAQ teaser', 'description' => 'The first few published FAQs. Hidden when there are none.'],
        'blog' => ['label' => 'Latest blog articles', 'description' => 'Pulled from your WordPress blog. Hidden when off or unreachable.'],
        'apps' => ['label' => 'Mobile apps band', 'description' => 'Google Play / App Store badges (Website → Mobile Apps). Hidden until a link is set.'],
        'cta' => ['label' => 'Final call to action', 'description' => 'The closing "ready to start?" banner.'],
    ];

    /**
     * Default copy for every text field. Keys are SiteSetting keys.
     *
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');

        return [
            'home_hero_eyebrow' => 'The all-in-one AI platform for affiliate marketers',
            'hero_headline' => 'Find the offer. Find the buyer. *Promote it everywhere.*',
            'hero_subheadline' => $siteName.' researches any product, finds real buyers on Google Maps and LinkedIn, writes your campaigns and content, and tracks every click and commission — built for African and diaspora entrepreneurs, sharp enough for affiliates anywhere.',
            'home_hero_primary_label' => 'See plans & pricing',
            'home_hero_primary_url' => '',
            'home_hero_secondary_label' => 'See how it works',
            'home_hero_secondary_url' => '#how-it-works',
            'home_hero_note' => '14-day money-back guarantee on every plan.',
            'home_logos_heading' => 'Trusted by marketers promoting',
            'home_how_heading' => 'From product idea to paid commission in *four steps*',
            'home_how_subheading' => $siteName.' follows the real affiliate journey, so you always know what to do next.',
            'home_features_heading' => 'Everything an affiliate marketer needs, in one place',
            'home_features_subheading' => 'From your first offer to your first tracked sale — research, buyers, campaigns, content and tracking, connected to your affiliate networks and your WordPress site, without stitching together a dozen tools.',
            'home_video_heading' => 'See '.$siteName.' in action',
            'home_video_subheading' => 'Watch one product go from research to real buyers, a full campaign and tracked clicks.',
            'home_video_points' => "Product research in minutes\nBuyers from Google Maps & LinkedIn\nCampaigns and content written for you",
            'home_stats_heading' => 'Built to replace your whole affiliate stack',
            'home_testimonials_heading' => 'What affiliates are saying',
            'home_pricing_heading' => 'Simple, transparent pricing',
            'home_pricing_subheading' => 'Every plan includes product research, buyer finding, AI content and tracking. Pick the credit allowance that fits how much you publish.',
            'home_faq_heading' => 'Questions, answered',
            'home_faq_subheading' => 'The short version. Everything else lives in our Help Centre.',
            'home_faq_count' => '4',
            'home_cta_heading' => 'Ready to find your next sale?',
            'home_cta_subtext' => 'Pick a plan and be researching your first product in minutes.',
            'home_cta_button_label' => 'See plans & pricing',
            'home_cta_button_url' => '',
            // Small labels and buttons that used to be fixed in the templates.
            'home_hero_works_with' => 'Works with the channels you already use',
            'home_how_eyebrow' => 'How it works',
            'home_how_step_link_label' => 'See how it works',
            'home_how_footer_text' => 'Want a closer look?',
            'home_how_footer_link' => 'Watch free tutorials in our Learning Centre',
            'home_features_eyebrow' => 'Features',
            'home_features_link_label' => 'Learn more',
            'home_features_all_label' => 'Explore all features',
            'home_video_eyebrow' => 'Product tour',
            'home_testimonials_eyebrow' => 'Testimonials',
            'home_pricing_eyebrow' => 'Pricing',
            'home_pricing_featured_label' => 'Most popular',
            'home_pricing_button_label' => 'Get started',
            'home_pricing_link_label' => 'See the full plan comparison',
            'home_faq_eyebrow' => 'FAQ',
            'home_faq_help_label' => 'See all questions',
            'home_faq_contact_label' => 'Ask us anything',
            'home_blog_eyebrow' => 'From the blog',
            'home_blog_link_label' => 'Visit our blog',
            'home_blog_read_label' => 'Read article',
            'home_apps_phone_text' => 'On your phone?',
            'home_apps_phone_link' => 'Open the right store for your device',
            'home_cta_secondary_label' => 'Talk to us',
        ];
    }

    /**
     * The journey shown in "How it works" when an admin hasn't written
     * their own steps.
     *
     * Each step ships with a real screenshot (public/images/features) that
     * an uploaded image_path replaces.
     *
     * @return array<int, array{icon: string, title: string, description: string, default_image: string, image_alt: string, link_url: string}>
     */
    public static function defaultSteps(): array
    {
        return [
            ['icon' => 'magnifying-glass', 'title' => 'Research a product', 'description' => 'Paste a product name or affiliate link. Get the ideal buyer, their pain points, the best channel and angle — plus Google Maps and LinkedIn keywords to find them — in minutes.', 'default_image' => 'offer-research.webp', 'image_alt' => 'AffilStack research results for a product, showing the ideal buyer and the best channel to promote it', 'link_url' => '/features/offer-research'],
            ['icon' => 'map-pin', 'title' => 'Find buyers on Maps & LinkedIn', 'description' => 'Pull real businesses from Google Maps and decision-makers from LinkedIn straight into a CRM list for that product — in one click with the Chrome extension.', 'default_image' => 'local-leads.webp', 'image_alt' => 'Google Maps business results ready to add to the CRM', 'link_url' => '/features/google-maps-lead-finder'],
            ['icon' => 'sparkles', 'title' => 'Launch AI campaigns & content', 'description' => 'A personalised email campaign to the whole list, sent from your own inbox — plus blog, LinkedIn, YouTube, UGC video, TikTok, X and Pinterest content written for you, with articles sent to your WordPress site as drafts.', 'default_image' => 'campaign-results.webp', 'image_alt' => 'An email campaign showing who opened, clicked and replied', 'link_url' => '/features/ai-email-campaigns'],
            ['icon' => 'banknotes', 'title' => 'Track clicks & earnings', 'description' => 'Every link click and commission in one dashboard, by product and by channel — with commissions imported from Impact, PartnerStack, Awin and CJ — so you know exactly what is paying.', 'default_image' => 'earnings.webp', 'image_alt' => 'Earnings by product and channel with pending, approved and paid totals', 'link_url' => '/features/earnings-tracker'],
        ];
    }

    /**
     * Factual product numbers — never invented social proof. An admin
     * replaces them with real results (members, payouts…) once they exist.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function defaultStats(): array
    {
        return [
            ['value' => '8', 'label' => 'content channels from a single product brief'],
            ['value' => '2', 'label' => 'buyer sources built in: Google Maps & LinkedIn'],
            ['value' => '1', 'label' => 'dashboard instead of a dozen separate tools'],
            ['value' => '14-day', 'label' => 'money-back guarantee on every plan'],
        ];
    }

    /**
     * Curated Heroicons an admin can pick for a step or feature.
     *
     * @return array<string, string>
     */
    public static function iconOptions(): array
    {
        return [
            'magnifying-glass' => 'Magnifying glass (research)',
            'map-pin' => 'Map pin (Google Maps)',
            'user-group' => 'People (buyers / leads)',
            'sparkles' => 'Sparkles (AI)',
            'envelope' => 'Envelope (email)',
            'pencil-square' => 'Pencil (content)',
            'video-camera' => 'Video camera (UGC / YouTube)',
            'megaphone' => 'Megaphone (campaigns)',
            'link' => 'Link (tracking links)',
            'chart-bar' => 'Bar chart (analytics)',
            'banknotes' => 'Banknotes (earnings)',
            'rocket-launch' => 'Rocket (launch)',
            'light-bulb' => 'Light bulb (ideas)',
            'device-phone-mobile' => 'Phone (mobile)',
            'globe-alt' => 'Globe (worldwide)',
            'shield-check' => 'Shield (trust)',
        ];
    }

    /**
     * The sections in the admin's chosen order, each with its on/off state.
     * Sections added in a later release (not yet in the saved list) are
     * appended, switched on, so they're never silently missing.
     *
     * @return array<int, array{key: string, label: string, description: string, enabled: bool}>
     */
    public static function sections(): array
    {
        $raw = SiteSetting::get('home_sections', '[]');
        $saved = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        $ordered = [];

        foreach ($saved as $row) {
            $key = is_array($row) ? ($row['key'] ?? null) : null;

            if (! is_string($key) || ! isset(self::SECTIONS[$key]) || isset($ordered[$key])) {
                continue;
            }

            $ordered[$key] = (bool) ($row['enabled'] ?? true);
        }

        foreach (array_keys(self::SECTIONS) as $key) {
            $ordered[$key] ??= true;
        }

        return array_map(
            fn (string $key, bool $enabled): array => [
                'key' => $key,
                'label' => self::SECTIONS[$key]['label'],
                'description' => self::SECTIONS[$key]['description'],
                'enabled' => $enabled,
            ],
            array_keys($ordered),
            array_values($ordered),
        );
    }

    /**
     * @return array<int, string> keys of the switched-on sections, in order
     */
    public static function enabledSectionKeys(): array
    {
        return array_values(array_map(
            fn (array $section): string => $section['key'],
            array_filter(static::sections(), fn (array $section): bool => $section['enabled']),
        ));
    }

    /**
     * An admin-entered value, or the default copy when it's blank.
     */
    public static function text(string $key): string
    {
        $value = SiteSetting::get($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return static::defaults()[$key] ?? '';
    }

    /**
     * A repeater-shaped (JSON list) setting, or $default when empty.
     *
     * @param  array<int, array<string, mixed>>  $default
     * @return array<int, array<string, mixed>>
     */
    public static function list(string $key, array $default = []): array
    {
        $raw = SiteSetting::get($key, '[]');
        $rows = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);
        $rows = array_values(array_filter($rows, fn ($row): bool => is_array($row) && filled($row['title'] ?? $row['value'] ?? null)));

        return $rows !== [] ? $rows : $default;
    }

    /**
     * @return array<int, array{icon: string, title: string, description: string}>
     */
    public static function steps(): array
    {
        $defaults = static::defaultSteps();
        $steps = static::list('home_steps', $defaults);

        // Steps saved before screenshots existed keep the shipped one for
        // their position until an image is uploaded or explicitly cleared.
        foreach ($steps as $index => $step) {
            if (! array_key_exists('default_image', $step) && isset($defaults[$index])) {
                $steps[$index] += array_intersect_key($defaults[$index], array_flip(['default_image', 'image_alt', 'link_url']));
            }
        }

        return $steps;
    }

    /**
     * A step's screenshot: an uploaded image, else its shipped default.
     *
     * @param  array<string, mixed>  $step
     */
    public static function stepImage(array $step): ?string
    {
        $uploaded = $step['image_path'] ?? null;

        return FeaturePage::imageUrl(is_array($uploaded) ? (reset($uploaded) ?: null) : $uploaded, $step['default_image'] ?? null);
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function stats(): array
    {
        return static::list('home_stats', static::defaultStats());
    }

    /**
     * A site-relative path or http(s) link an admin typed, or $fallback —
     * never a javascript:/data: URL. A bare "#anchor" is allowed too, for
     * in-page links such as "#how-it-works".
     */
    public static function url(string $key, string $fallback): string
    {
        $value = trim((string) SiteSetting::get($key, ''));

        if ($value === '') {
            $value = trim(static::defaults()[$key] ?? '');
        }

        if ($value !== '' && (str_starts_with($value, '#') || SafeHtml::isSafeUrl($value))) {
            return $value;
        }

        return $fallback;
    }

    /**
     * Escapes $text, then turns *wrapped words* into the gold highlight the
     * hero and section headings use — the one bit of formatting an admin
     * can add to a headline.
     */
    public static function highlight(string $text, string $class = 'text-gold-400'): HtmlString
    {
        $escaped = e($text);

        $html = preg_replace('/\*(.+?)\*/u', '<span class="'.e($class).'">$1</span>', $escaped) ?? $escaped;

        return new HtmlString($html);
    }

    /**
     * The same text with the *highlight* markers removed (for meta tags,
     * alt text and anywhere HTML isn't rendered).
     */
    public static function plain(string $text): string
    {
        return str_replace('*', '', $text);
    }

    /**
     * How many FAQs the teaser shows (3–6).
     */
    public static function faqCount(): int
    {
        return max(3, min(6, (int) static::text('home_faq_count')));
    }
}
