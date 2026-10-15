<?php

namespace App\Services\Blog;

use App\Models\CrmList;
use App\Models\FeaturePage;
use App\Models\SiteSetting;
use App\Support\Brand;
use App\Support\Website\NavigationMenu;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Throwable;

/**
 * Everything the AffilStack WordPress theme on blog.affilstack.com needs to
 * look and link exactly like the main website — logo, colours, the header
 * menu, footer columns, the end-of-post call to action and the newsletter
 * box — as one public JSON document (GET /api/blog/kit). The theme caches
 * it, so editing the menu or logo in AffilStack's admin updates the blog
 * within minutes.
 *
 * It holds only what the public website already shows. The connection key
 * the blog uses to add newsletter subscribers (blog_connection_key) is
 * never part of it.
 */
class BlogKit
{
    public const CACHE_KEY = 'blog_kit:v1';

    public const CACHE_SECONDS = 300;

    public const DEFAULT_CTA_HEADING = 'Turn this into commissions with AffilStack';

    public const DEFAULT_CTA_TEXT = 'Research any affiliate product, find buyers on Google Maps and LinkedIn, and launch campaigns and content from one dashboard.';

    public const DEFAULT_CTA_BUTTON = 'Start with AffilStack';

    public const DEFAULT_NEWSLETTER_HEADING = 'Get new playbooks in your inbox';

    public const DEFAULT_NEWSLETTER_TEXT = 'One practical affiliate-marketing email a week. No spam, unsubscribe any time.';

    public const DEFAULT_NEWSLETTER_BUTTON = 'Subscribe';

    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn (): array => static::build());
    }

    public static function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return array<string, mixed>
     */
    public static function build(): array
    {
        $data = [
            'site' => [
                'name' => Brand::name(),
                'url' => route('home'),
                'logo' => Brand::logoUrl(),
                'logo_white' => Brand::whiteLogoUrl(),
                'icon' => Brand::iconUrl(),
                'icon_white' => Brand::whiteIconUrl(),
                'favicon' => route('favicons.show', 'favicon-96x96.png'),
            ],
            'colors' => [
                'primary' => static::hex(SiteSetting::get('color_primary'), '#2452D9'),
                'navy' => static::hex(SiteSetting::get('color_navy'), '#0B1E3D'),
                'gold' => static::hex(SiteSetting::get('color_gold'), '#C9A24A'),
            ],
            'header' => [
                'tools_label' => NavigationMenu::label('nav_tools_label'),
                'tools' => static::tools(),
                'all_features' => ['label' => NavigationMenu::label('nav_all_features_label'), 'url' => route('features.index')],
                'resources_label' => NavigationMenu::label('nav_resources_label'),
                'resources' => array_map(fn (array $item): array => [
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'url' => $item['url'],
                ], array_values(array_filter(NavigationMenu::resources(), fn (array $item): bool => static::isAbsolute($item['url'])))),
                'pricing' => ['label' => NavigationMenu::label('nav_pricing_label'), 'url' => route('registration.pricing')],
                'expert' => ['label' => NavigationMenu::label('nav_expert_label'), 'url' => route('contact.show')],
                'login' => ['label' => NavigationMenu::label('nav_login_label'), 'url' => route('login')],
                'cta' => ['label' => NavigationMenu::label('nav_get_started_label'), 'url' => route('registration.pricing')],
            ],
            'footer' => [
                'tagline' => NavigationMenu::label('footer_tagline'),
                'columns' => array_values(array_filter(array_map(fn (array $column): array => [
                    'heading' => $column['heading'],
                    'links' => array_values(array_map(
                        fn (array $link): array => ['label' => $link['label'], 'url' => $link['url']],
                        array_filter($column['links'], fn (array $link): bool => static::isAbsolute($link['url'])),
                    )),
                ], NavigationMenu::footer()), fn (array $column): bool => $column['links'] !== [])),
                'copyright' => (string) SiteSetting::get('footer_text', '© '.date('Y').' '.Brand::name().'.'),
                'note' => NavigationMenu::label('footer_bottom_note'),
                'socials' => static::socials(),
            ],
            'cta_box' => [
                'enabled' => SiteSetting::flag('blog_cta_enabled', true),
                'heading' => static::text('blog_cta_heading', self::DEFAULT_CTA_HEADING),
                'text' => static::text('blog_cta_text', self::DEFAULT_CTA_TEXT),
                'button_label' => static::text('blog_cta_button_label', self::DEFAULT_CTA_BUTTON),
                'button_url' => static::ctaUrl(),
            ],
            'newsletter' => [
                'enabled' => static::newsletterEnabled(),
                'heading' => static::text('blog_newsletter_heading', self::DEFAULT_NEWSLETTER_HEADING),
                'text' => static::text('blog_newsletter_text', self::DEFAULT_NEWSLETTER_TEXT),
                'button_label' => static::text('blog_newsletter_button_label', self::DEFAULT_NEWSLETTER_BUTTON),
                'privacy_url' => route('privacy'),
            ],
        ];

        $data['version'] = substr(sha1((string) json_encode($data)), 0, 12);

        return $data;
    }

    public static function newsletterEnabled(): bool
    {
        return SiteSetting::flag('blog_newsletter_enabled', false) && static::newsletterList() !== null;
    }

    public static function newsletterList(): ?CrmList
    {
        $id = (int) SiteSetting::get('blog_newsletter_list_id');

        return $id > 0 ? CrmList::find($id) : null;
    }

    /**
     * The secret the blog sends with newsletter sign-ups. Generated on
     * first use and kept encrypted.
     */
    public static function connectionKey(): string
    {
        $stored = SiteSetting::get('blog_connection_key');

        if (is_string($stored) && $stored !== '') {
            try {
                return Crypt::decryptString($stored);
            } catch (Throwable) {
                // Re-issued below if the app key changed.
            }
        }

        return static::rotateConnectionKey();
    }

    public static function rotateConnectionKey(): string
    {
        $key = 'afsblog_'.Str::random(40);
        SiteSetting::set('blog_connection_key', Crypt::encryptString($key));

        return $key;
    }

    public static function isValidConnectionKey(?string $candidate): bool
    {
        return is_string($candidate) && $candidate !== '' && hash_equals(static::connectionKey(), $candidate);
    }

    /**
     * @return array<int, array{label: string, items: array<int, array{name: string, description: string, url: string}>}>
     */
    protected static function tools(): array
    {
        $groups = [];

        foreach (FeaturePage::menuGroups() as $key => $pages) {
            $groups[] = [
                'label' => NavigationMenu::label('nav_group_'.$key.'_label'),
                'items' => $pages->map(fn (FeaturePage $page): array => [
                    'name' => (string) $page->name,
                    'description' => (string) $page->menu_description,
                    'url' => $page->url(),
                ])->values()->all(),
            ];
        }

        return $groups;
    }

    /**
     * @return array<int, array{network: string, url: string}>
     */
    protected static function socials(): array
    {
        $socials = [];

        foreach (['facebook', 'twitter', 'linkedin', 'youtube', 'instagram'] as $network) {
            $url = trim((string) SiteSetting::get('seo_social_'.$network));

            if (static::isAbsolute($url)) {
                $socials[] = ['network' => $network, 'url' => $url];
            }
        }

        return $socials;
    }

    protected static function ctaUrl(): string
    {
        $url = trim((string) SiteSetting::get('blog_cta_button_url'));

        return static::isAbsolute($url) ? $url : route('registration.pricing');
    }

    protected static function text(string $key, string $default): string
    {
        $value = trim((string) SiteSetting::get($key));

        return $value !== '' ? $value : $default;
    }

    protected static function hex(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $default;
    }

    protected static function isAbsolute(string $url): bool
    {
        return (bool) preg_match('#^https?://#i', $url);
    }
}
