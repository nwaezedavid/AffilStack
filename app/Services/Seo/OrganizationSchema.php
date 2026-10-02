<?php

namespace App\Services\Seo;

use App\Models\SiteSetting;
use App\Support\Seo\SeoImage;

/**
 * The sitewide Organization + WebSite structured data, edited from SEO >
 * Organization schema and prefilled with sensible defaults from the brand
 * settings (site name, support email, default description, About-page
 * founder, social profiles) so it is complete before anyone opens the form.
 *
 * Everything is stored as one JSON SiteSetting (seo_organization). Empty
 * fields are dropped from the output rather than published blank, and
 * sameAs only ever lists profiles an admin actually entered.
 *
 * No WebSite SearchAction is emitted: the site has no search results page
 * for one to point at, and Google ignores (or flags) a fake one.
 */
class OrganizationSchema
{
    public const SETTING_KEY = 'seo_organization';

    /**
     * @var array<string, string>
     */
    public const CONTACT_TYPES = [
        'customer support' => 'Customer support',
        'technical support' => 'Technical support',
        'billing support' => 'Billing support',
        'sales' => 'Sales',
    ];

    /**
     * Social profile settings shared with the footer and Contact page.
     *
     * @var array<int, string>
     */
    public const SOCIAL_KEYS = [
        'seo_social_facebook',
        'seo_social_twitter',
        'seo_social_linkedin',
        'seo_social_instagram',
        'seo_social_youtube',
    ];

    public function __construct(protected FaviconGenerator $favicons) {}

    /**
     * Stored values merged over the defaults.
     *
     * @return array<string, mixed>
     */
    public function settings(): array
    {
        $raw = SiteSetting::get(self::SETTING_KEY);
        $stored = is_array($raw) ? $raw : (json_decode((string) $raw, true) ?: []);

        $settings = $this->defaults();

        foreach ($stored as $key => $value) {
            if ($value !== null && $value !== '' && $value !== []) {
                $settings[$key] = $value;
            }
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $founder = SiteSetting::get('about_founder_name');

        return [
            'name' => $siteName,
            'legal_name' => null,
            'alternate_name' => null,
            'url' => route('home'),
            'logo_path' => null,
            'description' => SiteSetting::get('seo_meta_description') ?: "{$siteName} is the all-in-one platform for affiliate marketers: offer research, AI-written content, UGC video, lead tracking and a built-in affiliate program.",
            'founding_date' => null,
            'founders' => $founder ? [['name' => $founder, 'url' => null]] : [],
            'email' => SiteSetting::get('support_email', 'support@affilstack.com'),
            'telephone' => null,
            'contact_type' => 'customer support',
            'street_address' => null,
            'address_locality' => null,
            'address_region' => null,
            'postal_code' => null,
            'address_country' => null,
            'extra_same_as' => [],
            'website_name' => $siteName,
            'website_alternate_name' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function save(array $data): void
    {
        SiteSetting::set(self::SETTING_KEY, json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array<int, string>
     */
    public function sameAs(): array
    {
        $settings = $this->settings();
        $profiles = array_map(fn (string $key) => SiteSetting::get($key), self::SOCIAL_KEYS);

        return array_values(array_unique(array_filter(
            [...$profiles, ...(array) ($settings['extra_same_as'] ?? [])],
            fn ($url) => is_string($url) && preg_match('#^https?://#i', $url),
        )));
    }

    /**
     * The organization's logo: a dedicated upload if there is one,
     * otherwise the 512×512 PNG generated from the square site icon —
     * always a crawlable, square PNG well above Google's 112×112 minimum.
     */
    public function logoUrl(): string
    {
        $path = $this->settings()['logo_path'] ?? null;

        return SeoImage::url(is_string($path) ? $path : null) ?? $this->favicons->url('favicon-512x512.png');
    }

    public function organizationId(): string
    {
        return route('home').'#organization';
    }

    public function websiteId(): string
    {
        return route('home').'#website';
    }

    /**
     * @return array<string, mixed>
     */
    public function organizationNode(): array
    {
        $s = $this->settings();

        $address = $this->clean([
            '@type' => 'PostalAddress',
            'streetAddress' => $s['street_address'],
            'addressLocality' => $s['address_locality'],
            'addressRegion' => $s['address_region'],
            'postalCode' => $s['postal_code'],
            'addressCountry' => $s['address_country'],
        ]);

        $contactPoint = ($s['email'] || $s['telephone']) ? $this->clean([
            '@type' => 'ContactPoint',
            'contactType' => $s['contact_type'] ?: 'customer support',
            'email' => $s['email'],
            'telephone' => $s['telephone'],
        ]) : null;

        $founders = collect((array) $s['founders'])
            ->filter(fn ($founder) => is_array($founder) && filled($founder['name'] ?? null))
            ->map(fn (array $founder) => $this->clean(['@type' => 'Person', 'name' => $founder['name'], 'url' => $founder['url'] ?? null]))
            ->values()
            ->all();

        $logo = $this->logoUrl();

        return $this->clean([
            '@type' => 'Organization',
            '@id' => $this->organizationId(),
            'name' => $s['name'],
            'legalName' => $s['legal_name'],
            'alternateName' => $s['alternate_name'],
            'url' => $s['url'],
            'logo' => ['@type' => 'ImageObject', 'url' => $logo, 'contentUrl' => $logo],
            'image' => $logo,
            'description' => $s['description'],
            'foundingDate' => $s['founding_date'],
            'founder' => $founders ?: null,
            'email' => $s['email'],
            'telephone' => $s['telephone'],
            'contactPoint' => $contactPoint,
            'address' => count($address) > 1 ? $address : null,
            'sameAs' => $this->sameAs() ?: null,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function websiteNode(): array
    {
        $s = $this->settings();

        return $this->clean([
            '@type' => 'WebSite',
            '@id' => $this->websiteId(),
            'name' => $s['website_name'] ?: $s['name'],
            'alternateName' => $s['website_alternate_name'],
            'url' => route('home'),
            'inLanguage' => 'en',
            'publisher' => ['@id' => $this->organizationId()],
        ]);
    }

    /**
     * Drops null/blank values so nothing is ever published empty.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function clean(array $data): array
    {
        return array_filter($data, fn ($value) => $value !== null && $value !== '' && $value !== []);
    }
}
