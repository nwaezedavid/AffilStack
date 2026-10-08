<?php

namespace App\Services\Seo;

use App\Models\SiteSetting;
use App\Support\Seo\IcoEncoder;
use App\Support\Seo\SeoImage;
use GdImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * Builds the full favicon set search engines and browsers look for from the
 * square logo uploaded in Site > Branding (or Site > Site icon), with GD
 * only:
 *
 *  - favicon.ico (a real ICO container holding 16, 32 and 48px PNGs)
 *  - favicon-48x48.png, -96x96, -192x192, -512x512 (multiples of 48px, as
 *    Google's favicon guidelines recommend)
 *  - apple-touch-icon.png (180px, on an opaque white background — iOS
 *    paints transparency black)
 *  - site.webmanifest (name, short name, icons, theme colour)
 *
 * The files live on the private "local" disk under favicons/ and are served
 * from stable root URLs (/favicon.ico, /favicon-48x48.png, ...) by
 * FaviconController — so they never depend on the /storage symlink, the
 * web-root layout on the host, or a writable public/ directory, and a code
 * deploy can never overwrite them.
 *
 * The set is regenerated eagerly whenever an admin saves a new icon, and
 * lazily (on the first favicon request) whenever its signature — icon
 * path, site name, brand colours — no longer matches what was built, so
 * a change made any other way (the Creative Agent, a direct DB edit, a
 * fresh deploy) self-heals without anyone running a command.
 */
class FaviconGenerator
{
    /** Bump to force every install to rebuild its set after a code change. */
    public const VERSION = 2;

    public const DIRECTORY = 'favicons';

    /**
     * Every served file and its content type.
     *
     * @var array<string, string>
     */
    public const FILES = [
        'favicon.ico' => 'image/x-icon',
        'favicon-48x48.png' => 'image/png',
        'favicon-96x96.png' => 'image/png',
        'favicon-192x192.png' => 'image/png',
        'favicon-512x512.png' => 'image/png',
        'apple-touch-icon.png' => 'image/png',
        'site.webmanifest' => 'application/manifest+json',
    ];

    /** Largest source we will decode (a small file can declare huge dimensions). */
    protected const MAX_SOURCE_PIXELS = 40_000_000;

    public function disk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * A fingerprint of every input the set is built from.
     */
    public function signature(): string
    {
        return md5((string) json_encode([
            self::VERSION,
            SiteSetting::get('logo_square_path'),
            SiteSetting::get('site_name', 'AffilStack'),
            SiteSetting::get('color_navy', '#0B1E3D'),
            SiteSetting::get('color_gold', '#C9A24A'),
        ]));
    }

    /**
     * Cache-busting token appended to every <link> href — changes only
     * when the icon (or the manifest's name/colour) changes, so the URLs
     * stay stable the way Google asks.
     */
    public function version(): string
    {
        return substr($this->signature(), 0, 10);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        $raw = $this->disk()->get(self::DIRECTORY.'/meta.json');

        return $raw ? (json_decode($raw, true) ?: null) : null;
    }

    public function isFresh(): bool
    {
        $meta = $this->meta();

        if (($meta['signature'] ?? null) !== $this->signature()) {
            return false;
        }

        foreach (array_keys(self::FILES) as $file) {
            if (! $this->disk()->exists(self::DIRECTORY.'/'.$file)) {
                return false;
            }
        }

        return true;
    }

    public function ensureFresh(): void
    {
        if (! $this->isFresh()) {
            $this->generate();
        }
    }

    public function contents(string $file): ?string
    {
        if (! array_key_exists($file, self::FILES)) {
            return null;
        }

        return $this->disk()->get(self::DIRECTORY.'/'.$file);
    }

    /**
     * Absolute URL of one generated file, versioned for cache busting.
     */
    public function url(string $file): string
    {
        return URL::to('/'.$file).'?v='.$this->version();
    }

    /**
     * (Re)builds the whole set.
     *
     * @return array{source: string, source_width: int, source_height: int, warnings: array<int, string>}
     */
    public function generate(): array
    {
        $warnings = [];
        $sourcePath = SiteSetting::get('logo_square_path');
        $source = $this->loadSource($sourcePath, $warnings);
        $sourceKind = $source ? 'upload' : 'default';
        $source ??= $this->defaultIcon();

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth !== $sourceHeight) {
            $warnings[] = "The uploaded icon is {$sourceWidth}×{$sourceHeight}, not square — it was centred on a transparent square canvas. Upload a square image for the sharpest result.";
        }

        if ($sourceKind === 'upload' && max($sourceWidth, $sourceHeight) < 512) {
            $warnings[] = 'The uploaded icon is smaller than 512×512, so the larger sizes are upscaled and may look soft.';
        }

        $square = $this->squareCanvas($source);
        $disk = $this->disk();
        $directory = self::DIRECTORY;

        foreach ([48, 96, 192, 512] as $size) {
            $disk->put("{$directory}/favicon-{$size}x{$size}.png", $this->png($this->resize($square, $size)));
        }

        $disk->put("{$directory}/apple-touch-icon.png", $this->png($this->onWhite($this->resize($square, 180))));
        $disk->put("{$directory}/favicon.ico", IcoEncoder::encode([
            $this->png($this->resize($square, 16)),
            $this->png($this->resize($square, 32)),
            $this->png($this->resize($square, 48)),
        ]));
        $disk->put("{$directory}/site.webmanifest", $this->manifest());

        $meta = [
            'signature' => $this->signature(),
            'source' => $sourceKind,
            'source_path' => $sourcePath,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
            'warnings' => $warnings,
            'generated_at' => now()->toIso8601String(),
        ];
        $disk->put("{$directory}/meta.json", (string) json_encode($meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        Cache::forget('favicons:meta');

        return [
            'source' => $sourceKind,
            'source_width' => $sourceWidth,
            'source_height' => $sourceHeight,
            'warnings' => $warnings,
        ];
    }

    /**
     * The uploaded square icon (or the default monogram) as a PNG of the
     * given size — used to brand the Chrome extension's toolbar icons.
     * Returns null when no icon has been uploaded.
     */
    public function uploadedIconPng(int $size): ?string
    {
        $warnings = [];
        $source = $this->loadSource(SiteSetting::get('logo_square_path'), $warnings);

        return $source ? $this->png($this->resize($this->squareCanvas($source), $size)) : null;
    }

    public function manifest(): string
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $themeColor = $this->hexColor(SiteSetting::get('color_navy'), '#0B1E3D');
        $version = $this->version();

        return (string) json_encode([
            'name' => $siteName,
            'short_name' => mb_substr($siteName, 0, 12),
            'start_url' => '/',
            'scope' => '/',
            'display' => 'browser',
            'theme_color' => $themeColor,
            'background_color' => '#ffffff',
            'icons' => [
                ['src' => "/favicon-192x192.png?v={$version}", 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => "/favicon-512x512.png?v={$version}", 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    public function themeColor(): string
    {
        return $this->hexColor(SiteSetting::get('color_navy'), '#0B1E3D');
    }

    /**
     * @param  array<int, string>  $warnings
     */
    protected function loadSource(mixed $path, array &$warnings): ?GdImage
    {
        if (! is_string($path) || $path === '') {
            return null;
        }

        $preferred = SeoImage::preferredPath($path);
        $publicDisk = Storage::disk('public');

        if (! $publicDisk->exists($preferred)) {
            $warnings[] = 'The uploaded icon file could not be found on disk, so a default monogram was used instead. Re-upload the icon.';

            return null;
        }

        $contents = (string) $publicDisk->get($preferred);
        $size = @getimagesizefromstring($contents);

        if (! $size || ($size[0] * $size[1]) > self::MAX_SOURCE_PIXELS) {
            $warnings[] = 'The uploaded icon could not be read as an image, so a default monogram was used instead.';

            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            $warnings[] = 'The uploaded icon could not be decoded, so a default monogram was used instead.';

            return null;
        }

        imagepalettetotruecolor($image);

        return $image;
    }

    protected function squareCanvas(GdImage $source): GdImage
    {
        $width = imagesx($source);
        $height = imagesy($source);
        $side = max($width, $height);

        if ($width === $height) {
            return $source;
        }

        $canvas = $this->transparentCanvas($side);
        imagecopy($canvas, $source, intdiv($side - $width, 2), intdiv($side - $height, 2), 0, 0, $width, $height);

        return $canvas;
    }

    /**
     * Halves repeatedly before the final resample — a single big jump from
     * 512px to 16px with GD's resampler aliases badly.
     */
    protected function resize(GdImage $source, int $size): GdImage
    {
        $current = $source;

        while (imagesx($current) >= $size * 4) {
            $half = intdiv(imagesx($current), 2);
            $next = $this->transparentCanvas($half);
            imagecopyresampled($next, $current, 0, 0, 0, 0, $half, $half, imagesx($current), imagesy($current));
            $current = $next;
        }

        $target = $this->transparentCanvas($size);
        imagecopyresampled($target, $current, 0, 0, 0, 0, $size, $size, imagesx($current), imagesy($current));

        return $target;
    }

    protected function onWhite(GdImage $image): GdImage
    {
        $size = imagesx($image);
        $canvas = imagecreatetruecolor($size, $size);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);
        imagecopy($canvas, $image, 0, 0, 0, 0, $size, $size);

        return $canvas;
    }

    protected function transparentCanvas(int $size): GdImage
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefilledrectangle($canvas, 0, 0, $size, $size, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagealphablending($canvas, true);

        return $canvas;
    }

    protected function png(GdImage $image): string
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * Used until an admin uploads a real icon: a rounded navy tile with a
     * gold "A" mark, in the brand colours — never a blank or broken icon.
     */
    protected function defaultIcon(): GdImage
    {
        $size = 512;
        $image = $this->transparentCanvas($size);
        [$nr, $ng, $nb] = $this->rgb($this->hexColor(SiteSetting::get('color_navy'), '#0B1E3D'));
        [$gr, $gg, $gb] = $this->rgb($this->hexColor(SiteSetting::get('color_gold'), '#C9A24A'));
        $navy = imagecolorallocate($image, $nr, $ng, $nb);
        $gold = imagecolorallocate($image, $gr, $gg, $gb);
        $radius = 104;

        imagefilledrectangle($image, $radius, 0, $size - $radius - 1, $size - 1, $navy);
        imagefilledrectangle($image, 0, $radius, $size - 1, $size - $radius - 1, $navy);

        foreach ([[$radius, $radius], [$size - $radius - 1, $radius], [$radius, $size - $radius - 1], [$size - $radius - 1, $size - $radius - 1]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $navy);
        }

        // An "A": two legs meeting at the apex, plus a crossbar.
        imagefilledpolygon($image, [256, 92, 300, 92, 420, 420, 354, 420, 256, 160, 158, 420, 92, 420, 212, 92], $gold);
        imagefilledrectangle($image, 180, 290, 334, 340, $gold);

        return $image;
    }

    protected function hexColor(mixed $value, string $fallback): string
    {
        return is_string($value) && preg_match('/^#[0-9a-fA-F]{6}$/', $value) ? strtoupper($value) : $fallback;
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    protected function rgb(string $hex): array
    {
        return [hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2))];
    }
}
