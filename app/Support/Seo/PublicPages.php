<?php

namespace App\Support\Seo;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Route;

/**
 * The route-based public pages whose SEO an admin controls from SEO > Page
 * SEO (SitePage rows — Terms, Privacy, custom pages — carry their own SEO
 * fields instead). Adding a public marketing route means adding it here.
 */
final class PublicPages
{
    /**
     * Schema.org types an admin may pick for a page. FAQPage is only
     * emitted by pages that actually render questions (Help, Affiliate);
     * anywhere else it degrades to WebPage rather than publishing an empty
     * FAQ that Google would flag as invalid.
     *
     * @var array<string, string>
     */
    public const SCHEMA_TYPES = [
        'WebPage' => 'Web page (general)',
        'AboutPage' => 'About page',
        'ContactPage' => 'Contact page',
        'FAQPage' => 'FAQ page',
        'CollectionPage' => 'Collection / listing page',
        'SoftwareApplication' => 'Software application (with plan prices as Offers)',
        'Product' => 'Product (with plan prices as Offers)',
        'none' => 'None — no page-level schema',
    ];

    /**
     * @return array<string, array{label: string, schema: string, description: string}>
     */
    public static function all(): array
    {
        return [
            'home' => ['label' => 'Homepage', 'schema' => 'WebPage', 'description' => 'The front door — the page Google shows for a brand search.'],
            'about' => ['label' => 'About', 'schema' => 'AboutPage', 'description' => 'Who you are and why you built the product.'],
            'registration.pricing' => ['label' => 'Pricing', 'schema' => 'SoftwareApplication', 'description' => 'Plans and prices — usually the highest-intent page.'],
            'help.index' => ['label' => 'Help & FAQ', 'schema' => 'FAQPage', 'description' => 'Help centre and published FAQs.'],
            'tutorials.index' => ['label' => 'Learning Centre', 'schema' => 'CollectionPage', 'description' => 'Video tutorials.'],
            'contact.show' => ['label' => 'Contact', 'schema' => 'ContactPage', 'description' => 'Contact form and support details.'],
            'affiliate.landing' => ['label' => 'Affiliate program', 'schema' => 'WebPage', 'description' => 'Affiliate program landing page (only live while the program is switched on).'],
        ];
    }

    public static function has(string $routeName): bool
    {
        return array_key_exists($routeName, self::all());
    }

    public static function label(string $routeName): string
    {
        return self::all()[$routeName]['label'] ?? $routeName;
    }

    public static function description(string $routeName): string
    {
        return self::all()[$routeName]['description'] ?? '';
    }

    public static function defaultSchemaType(string $routeName): string
    {
        return self::all()[$routeName]['schema'] ?? 'WebPage';
    }

    public static function url(string $routeName): ?string
    {
        return Route::has($routeName) ? route($routeName) : null;
    }

    /**
     * Whether the page is actually served right now — the affiliate
     * landing page disappears while the program is switched off.
     */
    public static function isLive(string $routeName): bool
    {
        if (! Route::has($routeName)) {
            return false;
        }

        return $routeName !== 'affiliate.landing' || SiteSetting::flag('affiliate_program_enabled');
    }
}
