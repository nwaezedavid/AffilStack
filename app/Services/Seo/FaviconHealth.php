<?php

namespace App\Services\Seo;

use App\Models\PageSeo;
use App\Models\SiteSetting;
use App\Support\Seo\IcoEncoder;
use App\Support\Seo\RobotsTxt;
use Illuminate\Support\Facades\Storage;

/**
 * The checklist on SEO > Site icon: every condition Google's "favicon in
 * search results" guidelines depend on, checked against this install —
 * including the two things that actually broke it at first launch (an
 * empty public/favicon.ico shadowing the real icon, and the icon being
 * converted to WebP, a format Google's favicon crawler doesn't accept).
 */
class FaviconHealth
{
    public function __construct(protected FaviconGenerator $favicons) {}

    /**
     * @return array<int, array{label: string, status: string, hint: string}>
     */
    public function checks(): array
    {
        $checks = [];
        $iconPath = SiteSetting::get('logo_square_path');
        $hasUpload = is_string($iconPath) && $iconPath !== '' && Storage::disk('public')->exists($iconPath);
        $meta = $this->favicons->meta();

        $checks[] = $hasUpload
            ? $this->check('pass', 'A square site icon is uploaded', 'Used to build every favicon size.')
            : $this->check('warn', 'No site icon uploaded yet — a default monogram is being used', 'Upload your square logo (PNG, 512×512 or larger) above.');

        if ($meta) {
            $square = $meta['source_width'] === $meta['source_height'];
            $big = min($meta['source_width'], $meta['source_height']) >= 512;
            $checks[] = $this->check(
                $square && $big ? 'pass' : 'warn',
                "Source image is {$meta['source_width']}×{$meta['source_height']}",
                'Google needs a square icon; 512×512 or larger keeps every size sharp.',
            );
        }

        $ico = $this->favicons->contents('favicon.ico');
        $sizes = array_map(fn (array $entry) => $entry['width'], IcoEncoder::entries((string) $ico));
        $checks[] = $this->check(
            $this->favicons->isFresh() && $sizes === [16, 32, 48] ? 'pass' : 'fail',
            $sizes ? 'favicon.ico contains '.implode(', ', array_map(fn ($size) => "{$size}×{$size}", $sizes)).' PNG images' : 'The favicon set has not been generated yet',
            'Plus 48, 96, 192 and 512px PNGs, a 180px Apple touch icon and site.webmanifest — all PNG, never WebP.',
        );

        $staticIcon = public_path('favicon.ico');
        $checks[] = is_file($staticIcon)
            ? $this->check('fail', 'A static public/favicon.ico file is overriding the real icon ('.filesize($staticIcon).' bytes)', 'Delete public/favicon.ico (public_html/favicon.ico on Hostinger) — or run "php artisan seo:generate-favicons", which removes an empty one.')
            : $this->check('pass', '/favicon.ico is served from the generated set', 'Same host as the homepage, with long-lived caching.');

        $staticRobots = public_path('robots.txt');
        $checks[] = is_file($staticRobots)
            ? $this->check('fail', 'A static public/robots.txt is overriding the robots.txt you edit in SEO settings', 'Delete public/robots.txt so /robots.txt is served from SEO settings (with your sitemap line).')
            : $this->check('pass', 'robots.txt is served from SEO settings', 'It always lists your sitemap.');

        $blocked = RobotsTxt::blockedEssentialPaths(RobotsTxt::content());
        $checks[] = $blocked === []
            ? $this->check('pass', 'robots.txt lets Googlebot and Googlebot-Image crawl the homepage and the icon', 'Required for the icon to be picked up.')
            : $this->check('fail', 'robots.txt blocks '.implode(', ', $blocked), 'Remove the matching Disallow lines in SEO settings.');

        $home = PageSeo::forRoute('home');
        $checks[] = ($home?->robots_index ?? true)
            ? $this->check('pass', 'The homepage is indexable', 'Google only reads the favicon from an indexable homepage.')
            : $this->check('fail', 'The homepage is set to noindex', 'Turn indexing back on under SEO > Page SEO > Homepage > Advanced.');

        $appUrl = (string) config('app.url');
        $checks[] = str_starts_with($appUrl, 'https://')
            ? $this->check('pass', "Icons are linked from {$appUrl}", 'Absolute HTTPS URLs on the same host as the homepage.')
            : $this->check('warn', "APP_URL is {$appUrl}", 'Set APP_URL in .env to your live https:// domain (e.g. https://affilstack.com) so every icon, canonical and sitemap URL uses it.');

        return $checks;
    }

    /**
     * @return array{label: string, status: string, hint: string}
     */
    protected function check(string $status, string $label, string $hint): array
    {
        return compact('status', 'label', 'hint');
    }
}
