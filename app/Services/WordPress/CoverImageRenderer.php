<?php

namespace App\Services\WordPress;

use App\Models\Generation;
use App\Services\Referrals\MarketingKit\BannerFonts;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * A featured image for an article sent to WordPress: 1200×630 (the size
 * Facebook, LinkedIn and most themes expect), the article title set in the
 * brand's Onest typeface over a navy gradient, with the product name above
 * it. Drawn with GD; returns null when GD or FreeType isn't available so
 * the article is simply sent without one.
 */
class CoverImageRenderer
{
    public const WIDTH = 1200;

    public const HEIGHT = 630;

    public function __construct(protected BannerFonts $fonts) {}

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagepng') && BannerFonts::available();
    }

    /**
     * Path on the public disk of the cover for this article, drawing it the
     * first time.
     */
    public function coverFor(Generation $generation): ?string
    {
        if (! static::available()) {
            return null;
        }

        $path = 'wordpress-covers/'.$generation->id.'-'.substr(sha1((string) ($generation->output_meta['title'] ?? '')), 0, 8).'.png';

        if (Storage::disk('public')->exists($path)) {
            return $path;
        }

        try {
            $png = $this->render(
                (string) ($generation->output_meta['title'] ?? 'New article'),
                (string) ($generation->offer?->product_name ?? ''),
            );
        } catch (Throwable) {
            return null;
        }

        Storage::disk('public')->put($path, $png);

        return $path;
    }

    public function render(string $title, string $eyebrow): string
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);

        // Diagonal navy → blue gradient, drawn as vertical bands.
        [$r1, $g1, $b1] = [11, 30, 61];
        [$r2, $g2, $b2] = [36, 82, 217];
        for ($x = 0; $x < self::WIDTH; $x++) {
            $t = ($x / self::WIDTH) ** 1.6 * 0.75;
            $color = imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imageline($image, $x, 0, $x, self::HEIGHT, $color);
        }

        // A soft gold arc in the corner, for depth.
        imagealphablending($image, true);
        $glow = imagecolorallocatealpha($image, 201, 162, 74, 112);
        imagefilledellipse($image, self::WIDTH - 60, self::HEIGHT + 40, 620, 620, $glow);

        $white = imagecolorallocate($image, 255, 255, 255);
        $gold = imagecolorallocate($image, 224, 194, 122);
        $pad = 84;
        $maxWidth = self::WIDTH - $pad * 2;

        $eyebrow = Str::upper(Str::limit(trim($eyebrow), 48, ''));
        $fit = $this->fonts->fit($title, 'extrabold', $maxWidth, 4, 72, 40, 330, 1.12);
        $lineHeight = $fit['px'] * 1.12;
        $blockTop = (self::HEIGHT - $fit['height']) / 2 + ($eyebrow !== '' ? 24 : 0);

        if ($eyebrow !== '') {
            imagettftext($image, 22 * BannerFonts::POINTS_PER_PIXEL, 0, $pad, (int) ($blockTop - 34), $gold, BannerFonts::path('medium'), $eyebrow);
        }

        $baseline = $blockTop + 0.72 * $fit['px'];
        foreach ($fit['lines'] as $line) {
            imagettftext($image, $fit['px'] * BannerFonts::POINTS_PER_PIXEL, 0, $pad, (int) $baseline, $white, BannerFonts::path('extrabold'), $line);
            $baseline += $lineHeight;
        }

        // Short gold accent rule near the bottom edge.
        imagefilledrectangle($image, $pad, self::HEIGHT - 70, $pad + 72, self::HEIGHT - 64, $gold);

        ob_start();
        imagepng($image, null, 6);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }
}
