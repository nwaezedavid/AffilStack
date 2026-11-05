<?php

namespace App\Support\Website;

use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Services\Blog\BlogFeed;
use App\Support\MobileApps;
use App\Support\SafeHtml;
use App\Support\SiteDomains;
use Illuminate\Support\Facades\Route;

/**
 * The public header and footer menus — every label, description, order
 * and on/off switch, edited in Website → Navigation menu. Links point at
 * named "destinations" (Pricing, FAQ, Blog…) rather than raw URLs, so they
 * follow the site when routes change and a destination that isn't live
 * (no blog URL yet, affiliate program switched off) quietly disappears.
 */
class NavigationMenu
{
    /**
     * @return array<string, string>
     */
    public static function labelDefaults(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');

        return [
            'nav_tools_label' => 'Tools',
            'nav_resources_label' => 'Resources',
            'nav_pricing_label' => 'Pricing',
            'nav_expert_label' => 'Talk To An Expert',
            'nav_login_label' => 'Login',
            'nav_get_started_label' => 'Get Started',
            'nav_dashboard_label' => 'Dashboard',
            'nav_all_features_label' => 'All features',
            'nav_all_features_description' => 'Every tool on one page, with screenshots.',
            'nav_tools_promo_eyebrow' => 'New here?',
            'nav_tools_promo_heading' => 'See how '.$siteName.' turns one product into paid commissions',
            'nav_tools_promo_label' => 'How it works',
            'nav_group_research_label' => FeaturePage::GROUPS['research'],
            'nav_group_outreach_label' => FeaturePage::GROUPS['outreach'],
            'nav_group_content_label' => FeaturePage::GROUPS['content'],
            'nav_group_grow_label' => FeaturePage::GROUPS['grow'],
            'footer_tagline' => 'The all-in-one AI platform for affiliate marketers — research a product, find buyers, run campaigns and track every commission from one dashboard.',
            'footer_bottom_note' => 'Built for affiliate marketers in Africa, the diaspora and worldwide.',
        ];
    }

    public static function label(string $key): string
    {
        $value = SiteSetting::get($key);

        return is_string($value) && trim($value) !== '' ? trim($value) : (static::labelDefaults()[$key] ?? '');
    }

    /**
     * Pages a menu item can point at. `icon` is a Heroicon outline name.
     *
     * @return array<string, array{label: string, description: string, icon: string}>
     */
    public static function destinations(): array
    {
        return [
            'home' => ['label' => 'Home', 'description' => 'The homepage.', 'icon' => 'home'],
            'how-it-works' => ['label' => 'How it works', 'description' => 'The four-step journey on the homepage.', 'icon' => 'map'],
            'features' => ['label' => 'Features', 'description' => 'Every tool, with screenshots.', 'icon' => 'squares-2x2'],
            'pricing' => ['label' => 'Pricing', 'description' => 'Plans and prices.', 'icon' => 'currency-dollar'],
            'blog' => ['label' => 'Blog', 'description' => 'Guides and strategies from our blog.', 'icon' => 'newspaper'],
            'faq' => ['label' => 'Frequently Asked Questions', 'description' => 'Quick answers before you start.', 'icon' => 'question-mark-circle'],
            'marketplace' => ['label' => 'Add-on Marketplace', 'description' => 'Apps, template packs and automation recipes.', 'icon' => 'puzzle-piece'],
            'developers' => ['label' => 'Developers', 'description' => 'Build and sell add-ons for AffilStack.', 'icon' => 'code-bracket'],
            'affiliate' => ['label' => 'Affiliate Program', 'description' => 'Earn recurring commission by referring us.', 'icon' => 'gift'],
            'help' => ['label' => 'Help Centre', 'description' => 'Support, guides and tutorials.', 'icon' => 'lifebuoy'],
            'learn' => ['label' => 'Learning Centre', 'description' => 'Free video tutorials.', 'icon' => 'academic-cap'],
            'contact' => ['label' => 'Contact', 'description' => 'Send us a message.', 'icon' => 'chat-bubble-left-right'],
            'about' => ['label' => 'About', 'description' => 'Who we are.', 'icon' => 'building-office'],
            'mobile-apps' => ['label' => 'Mobile apps', 'description' => 'Get the app.', 'icon' => 'device-phone-mobile'],
            'login' => ['label' => 'Sign in', 'description' => 'Log in to your account.', 'icon' => 'arrow-right-end-on-rectangle'],
            'terms' => ['label' => 'Terms', 'description' => 'Terms of service.', 'icon' => 'document-text'],
            'privacy' => ['label' => 'Privacy', 'description' => 'Privacy policy.', 'icon' => 'shield-check'],
            'refunds' => ['label' => 'Refunds', 'description' => 'Refund policy.', 'icon' => 'receipt-refund'],
            'cookies' => ['label' => 'Cookies', 'description' => 'Cookie policy.', 'icon' => 'finger-print'],
            // Agent 04's consent banner re-opens on this link (public/js/afs-consent.js).
            'cookie-settings' => ['label' => 'Cookie settings', 'description' => 'Change your cookie choices.', 'icon' => 'adjustments-horizontal'],
            'custom' => ['label' => 'Custom link', 'description' => '', 'icon' => 'link'],
        ];
    }

    /**
     * @return array<string, string> destination key => label, for selects
     */
    public static function destinationOptions(): array
    {
        return array_map(fn (array $destination): string => $destination['label'], static::destinations());
    }

    /**
     * The live URL of a destination, or null when it isn't available.
     */
    public static function urlFor(string $key, ?string $customUrl = null): ?string
    {
        $onHome = request()->routeIs('home');
        $homeAnchor = fn (string $anchor): string => $onHome ? '#'.$anchor : route('home').'#'.$anchor;
        $route = fn (string $name): ?string => Route::has($name) ? route($name) : null;

        return match ($key) {
            'home' => route('home'),
            'how-it-works' => $homeAnchor('how-it-works'),
            'features' => $route('features.index'),
            'pricing' => $route('registration.pricing'),
            'blog' => BlogFeed::baseUrl(),
            'faq' => $route('faq.index'),
            'marketplace' => $route('marketplace.index'),
            'developers' => $route('developer.home'),
            'affiliate' => SiteSetting::flag('affiliate_program_enabled') ? $route('affiliate.landing') : null,
            'help' => $route('help.index'),
            'learn' => $route('tutorials.index'),
            'contact' => $route('contact.show'),
            'about' => $route('about'),
            'mobile-apps' => MobileApps::isVisible() ? $homeAnchor('get-the-app') : null,
            'login' => SiteDomains::visitorIsSignedIn() ? null : $route('login'),
            'terms' => $route('terms'),
            'privacy' => $route('privacy'),
            'refunds' => $route('refund-policy'),
            'cookies' => $route('cookie-policy'),
            'cookie-settings' => '#cookie-settings',
            'custom' => $customUrl && (str_starts_with(trim($customUrl), '#') || SafeHtml::isSafeUrl($customUrl)) ? trim($customUrl) : null,
            default => null,
        };
    }

    public static function isExternal(string $key, ?string $url): bool
    {
        if ($key === 'blog') {
            return true;
        }

        return $url !== null && str_starts_with($url, 'http') && parse_url($url, PHP_URL_HOST) !== request()->getHost();
    }

    /**
     * @return array<int, array{key: string, label: string, description: string, visible: bool, url?: string}>
     */
    public static function defaultResources(): array
    {
        $item = fn (string $key, bool $visible = true): array => [
            'key' => $key,
            'label' => static::destinations()[$key]['label'],
            'description' => static::destinations()[$key]['description'],
            'visible' => $visible,
        ];

        return [$item('blog'), $item('marketplace'), $item('faq'), $item('affiliate'), $item('help'), $item('developers', false), $item('learn', false)];
    }

    /**
     * The Resources dropdown, resolved: only visible items whose
     * destination is live. Older custom header links (Site → Brand
     * settings "menu items") are appended so nothing an admin added
     * before this menu existed is lost.
     *
     * @return array<int, array{label: string, description: string, url: string, icon: string, external: bool}>
     */
    public static function resources(): array
    {
        $rows = static::storedList('nav_resources') ?? static::defaultResources();
        $items = [];

        foreach ($rows as $row) {
            $key = (string) ($row['key'] ?? '');

            if (! ($row['visible'] ?? true) || ! isset(static::destinations()[$key])) {
                continue;
            }

            $url = static::urlFor($key, $row['url'] ?? null);

            if ($url === null) {
                continue;
            }

            $items[] = [
                'label' => trim((string) ($row['label'] ?? '')) ?: static::destinations()[$key]['label'],
                'description' => trim((string) ($row['description'] ?? '')),
                'url' => $url,
                'icon' => static::destinations()[$key]['icon'],
                'external' => static::isExternal($key, $url),
            ];
        }

        foreach (static::legacyMenuItems() as $legacy) {
            $items[] = ['label' => $legacy['label'], 'description' => '', 'url' => $legacy['url'], 'icon' => 'link', 'external' => static::isExternal('custom', $legacy['url'])];
        }

        return $items;
    }

    /**
     * @return array<int, array{heading: string, links: array<int, array{key: string, label: string, visible: bool, url?: string}>}>
     */
    public static function defaultFooter(): array
    {
        $link = fn (string $key, ?string $label = null): array => ['key' => $key, 'label' => $label ?? static::destinations()[$key]['label'], 'visible' => true];

        return [
            ['heading' => 'Product', 'links' => [$link('features'), $link('how-it-works'), $link('pricing'), $link('marketplace'), $link('mobile-apps')]],
            ['heading' => 'Resources', 'links' => [$link('learn'), $link('blog', 'Visit our blog'), $link('faq', 'FAQ'), $link('help'), $link('developers')]],
            ['heading' => 'Company', 'links' => [$link('about'), $link('affiliate'), $link('contact'), $link('login')]],
            ['heading' => 'Legal', 'links' => [$link('terms'), $link('privacy'), $link('refunds'), $link('cookies'), $link('cookie-settings')]],
        ];
    }

    /**
     * The footer columns, resolved like resources().
     *
     * @return array<int, array{heading: string, links: array<int, array{label: string, url: string, external: bool}>}>
     */
    public static function footer(): array
    {
        $columns = static::storedList('nav_footer') ?? static::defaultFooter();
        $resolved = [];

        foreach ($columns as $column) {
            $links = [];

            foreach ((array) ($column['links'] ?? []) as $row) {
                $key = (string) ($row['key'] ?? '');

                if (! is_array($row) || ! ($row['visible'] ?? true) || ! isset(static::destinations()[$key])) {
                    continue;
                }

                $url = static::urlFor($key, $row['url'] ?? null);

                if ($url !== null) {
                    $links[] = [
                        'label' => trim((string) ($row['label'] ?? '')) ?: static::destinations()[$key]['label'],
                        'url' => $url,
                        'external' => static::isExternal($key, $url),
                    ];
                }
            }

            if ($links !== []) {
                $resolved[] = ['heading' => trim((string) ($column['heading'] ?? '')), 'links' => $links];
            }
        }

        return $resolved;
    }

    /**
     * Where "Talk To An Expert" goes: the Support page for customers, the
     * contact form for everyone else.
     */
    public static function expertUrl(): string
    {
        return SiteDomains::visitorIsSignedIn() && Route::has('support.index') ? route('support.index') : route('contact.show');
    }

    /**
     * The header's main button: Get Started (payment-first signup via
     * Pricing) for guests, Dashboard once signed in.
     *
     * @return array{label: string, url: string}
     */
    public static function primaryCta(): array
    {
        if (SiteDomains::visitorIsSignedIn()) {
            return ['label' => static::label('nav_dashboard_label'), 'url' => route('dashboard')];
        }

        return ['label' => static::label('nav_get_started_label'), 'url' => route('registration.pricing')];
    }

    /**
     * @return array<int, array<string, mixed>>|null null when never saved
     */
    public static function storedList(string $key): ?array
    {
        $raw = SiteSetting::get($key);

        if ($raw === null || $raw === '') {
            return null;
        }

        $rows = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($rows) ? array_values(array_filter($rows, 'is_array')) : null;
    }

    /**
     * The legacy free-form header links (menu_items). Accepts both the
     * persisted JSON string and the raw array a Tony preview passes.
     *
     * @return array<int, array{label: string, url: string}>
     */
    public static function legacyMenuItems(): array
    {
        $raw = SiteSetting::get('menu_items', '[]');
        $items = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        return array_values(array_map(
            fn (array $item): array => ['label' => (string) ($item['label'] ?? ''), 'url' => trim((string) $item['url'])],
            array_filter($items, fn ($item): bool => is_array($item) && filled($item['label'] ?? null) && SafeHtml::isSafeUrl((string) ($item['url'] ?? ''))),
        ));
    }
}
