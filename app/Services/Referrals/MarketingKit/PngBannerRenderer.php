<?php

namespace App\Services\Referrals\MarketingKit;

use GdImage;
use RuntimeException;

/**
 * Draws a BannerScene as a PNG with GD — for email clients and forums that
 * won't show SVG. Shapes are drawn supersampled (GD's own filled shapes have
 * no anti-aliasing) and scaled down with resampling, which gives clean
 * edges; text is then drawn at the final resolution with FreeType through
 * the bundled Onest TTFs, so it is properly anti-aliased and matches the SVG
 * glyph for glyph. Small banners are rendered at 2× so they stay sharp on
 * high-density screens when shown at their nominal width/height.
 *
 * Each translucent shape is painted onto its own transparent layer (alpha
 * baked into the colour, blending off) and then composited, so overlapping
 * pieces of one shape — e.g. a rounded rectangle's corners — never
 * double-darken.
 */
class PngBannerRenderer
{
    /**
     * Bump to invalidate every cached PNG after a renderer change.
     */
    public const VERSION = 3;

    private const SUPERSAMPLE = 2;

    public function __construct(private BannerFonts $fonts) {}

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagepng') && BannerFonts::available();
    }

    /**
     * Pixel density the PNG is rendered at: 2× for display banners, 1× for
     * the (already large) social formats.
     */
    public static function scaleFor(BannerScene $scene): int
    {
        return max($scene->width, $scene->height) <= 1000 ? 2 : 1;
    }

    /**
     * Rendered PNG bytes, cached on the local disk under the scene's
     * fingerprint so each design/size is only ever drawn once per deploy.
     */
    public function cached(BannerScene $scene, string $name, bool $transparent = false): string
    {
        $path = storage_path('app/affiliate-banners/'.$name.'-'.$scene->fingerprint().'.png');

        if (is_file($path)) {
            return (string) file_get_contents($path);
        }

        $png = $this->render($scene, $transparent);

        if (! is_dir(dirname($path))) {
            @mkdir(dirname($path), 0755, true);
        }
        @file_put_contents($path, $png);

        return $png;
    }

    public function render(BannerScene $scene, bool $transparent = false): string
    {
        if (! self::available()) {
            throw new RuntimeException('PNG banners need GD with FreeType and the bundled Onest font.');
        }

        $scale = self::scaleFor($scene);
        $factor = $scale * self::SUPERSAMPLE;
        $canvas = $this->blank((int) round($scene->width * $factor), (int) round($scene->height * $factor), $transparent);
        imagealphablending($canvas, true);

        foreach ($scene->shapes() as $element) {
            $this->shape($canvas, $element, $factor);
        }

        $final = $this->blank($scene->width * $scale, $scene->height * $scale, true);
        imagealphablending($final, false);
        imagecopyresampled($final, $canvas, 0, 0, 0, 0, $scene->width * $scale, $scene->height * $scale, imagesx($canvas), imagesy($canvas));
        unset($canvas);

        imagealphablending($final, true);
        foreach ($scene->texts() as $element) {
            imagettftext(
                $final,
                $element['size'] * $scale * BannerFonts::POINTS_PER_PIXEL,
                0,
                (int) round($element['x'] * $scale),
                (int) round($element['y'] * $scale),
                $this->color($final, $element['color'], $element['opacity']),
                BannerFonts::path($element['weight']),
                $element['text'],
            );
        }

        imagesavealpha($final, true);
        ob_start();
        imagepng($final, null, 9);

        return (string) ob_get_clean();
    }

    private function blank(int $width, int $height, bool $transparent): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width), max(1, $height));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefilledrectangle($image, 0, 0, $width, $height, imagecolorallocatealpha($image, 255, 255, 255, $transparent ? 127 : 0));

        return $image;
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function shape(GdImage $canvas, array $element, float $f): void
    {
        if ($element['type'] === 'glow') {
            $this->glow($canvas, $element, $f);

            return;
        }

        [$x1, $y1, $x2, $y2] = $this->bounds($element);
        $ox = (int) floor($x1 * $f) - 2;
        $oy = (int) floor($y1 * $f) - 2;
        $layer = $this->blank((int) ceil(($x2 - $x1) * $f) + 4, (int) ceil(($y2 - $y1) * $f) + 4, true);

        $tx = fn (float $x): float => $x * $f - $ox;
        $ty = fn (float $y): float => $y * $f - $oy;
        $opacity = (float) $element['opacity'];

        switch ($element['type']) {
            case 'rect':
                $this->rect($layer, $tx($element['x']), $ty($element['y']), $element['w'] * $f, $element['h'] * $f, $element['r'] * $f, $element['fill'], $opacity);
                break;
            case 'circle':
                $this->disc($layer, $tx($element['cx']), $ty($element['cy']), $element['r'] * $f, $this->color($layer, $element['fill'], $opacity));
                break;
            case 'ring':
                $this->disc($layer, $tx($element['cx']), $ty($element['cy']), $element['r'] * $f, $this->color($layer, $element['color'], $opacity));
                $this->disc($layer, $tx($element['cx']), $ty($element['cy']), ($element['r'] - $element['width']) * $f, imagecolorallocatealpha($layer, 0, 0, 0, 127));
                break;
            case 'polygon':
                $points = [];
                foreach ($element['points'] as [$px, $py]) {
                    $points[] = (int) round($tx($px));
                    $points[] = (int) round($ty($py));
                }
                imagefilledpolygon($layer, $points, $this->color($layer, $element['fill'], $opacity));
                break;
            case 'line':
                $this->polyline($layer, array_map(fn (array $point): array => [$tx($point[0]), $ty($point[1])], $element['points']), $element['width'] * $f, $this->color($layer, $element['color'], $opacity));
                break;
        }

        imagecopy($canvas, $layer, $ox, $oy, 0, 0, imagesx($layer), imagesy($layer));
    }

    /**
     * @param  array<string, mixed>  $element
     * @return array{0: float, 1: float, 2: float, 3: float}
     */
    private function bounds(array $element): array
    {
        return match ($element['type']) {
            'rect' => [$element['x'], $element['y'], $element['x'] + $element['w'], $element['y'] + $element['h']],
            'circle', 'ring' => [$element['cx'] - $element['r'], $element['cy'] - $element['r'], $element['cx'] + $element['r'], $element['cy'] + $element['r']],
            default => (function () use ($element): array {
                $pad = ($element['width'] ?? 0) / 2;
                $xs = array_column($element['points'], 0);
                $ys = array_column($element['points'], 1);

                return [min($xs) - $pad, min($ys) - $pad, max($xs) + $pad, max($ys) + $pad];
            })(),
        };
    }

    /**
     * @param  string|array{from: string, to: string, dir: string}  $fill
     */
    private function rect(GdImage $layer, float $x, float $y, float $w, float $h, float $r, string|array $fill, float $opacity): void
    {
        $x1 = (int) round($x);
        $y1 = (int) round($y);
        $x2 = (int) round($x + $w) - 1;
        $y2 = (int) round($y + $h) - 1;

        if (is_array($fill)) {
            [$fr, $fg, $fb] = $this->rgb($fill['from']);
            [$tr, $tg, $tb] = $this->rgb($fill['to']);
            $alpha = (int) round(127 * (1 - $opacity));
            $steps = $fill['dir'] === 'h' ? $x2 - $x1 : $y2 - $y1;

            for ($i = 0; $i <= $steps; $i++) {
                $t = $steps > 0 ? $i / $steps : 0;
                $color = imagecolorallocatealpha($layer, (int) round($fr + ($tr - $fr) * $t), (int) round($fg + ($tg - $fg) * $t), (int) round($fb + ($tb - $fb) * $t), $alpha);
                $fill['dir'] === 'h'
                    ? imageline($layer, $x1 + $i, $y1, $x1 + $i, $y2, $color)
                    : imageline($layer, $x1, $y1 + $i, $x2, $y1 + $i, $color);
            }
        } else {
            imagefilledrectangle($layer, $x1, $y1, $x2, $y2, $this->color($layer, $fill, $opacity));
        }

        if ($r < 1) {
            return;
        }

        $clear = imagecolorallocatealpha($layer, 0, 0, 0, 127);
        $size = (int) ceil($r);
        $right = $x2 + 1;
        $bottom = $y2 + 1;

        // Each corner: [first column, first row, circle centre x, circle centre y].
        $corners = [
            [$x1, $y1, $x1 + $r, $y1 + $r],
            [$right - $size, $y1, $right - $r, $y1 + $r],
            [$x1, $bottom - $size, $x1 + $r, $bottom - $r],
            [$right - $size, $bottom - $size, $right - $r, $bottom - $r],
        ];

        foreach ($corners as [$sx, $sy, $cx, $cy]) {
            for ($py = $sy; $py < $sy + $size; $py++) {
                for ($px = $sx; $px < $sx + $size; $px++) {
                    if (($px + 0.5 - $cx) ** 2 + ($py + 0.5 - $cy) ** 2 > $r * $r) {
                        imagesetpixel($layer, $px, $py, $clear);
                    }
                }
            }
        }
    }

    private function disc(GdImage $image, float $cx, float $cy, float $r, int $color): void
    {
        if ($r <= 0) {
            return;
        }

        imagefilledellipse($image, (int) round($cx), (int) round($cy), (int) round($r * 2), (int) round($r * 2), $color);
    }

    /**
     * A thick polyline: one quad per segment plus a disc at every vertex for
     * round caps and joins (matches SVG's stroke-linecap/linejoin="round").
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function polyline(GdImage $image, array $points, float $width, int $color): void
    {
        $half = $width / 2;

        for ($i = 0; $i < count($points) - 1; $i++) {
            [$ax, $ay] = $points[$i];
            [$bx, $by] = $points[$i + 1];
            $length = hypot($bx - $ax, $by - $ay) ?: 1;
            $nx = -($by - $ay) / $length * $half;
            $ny = ($bx - $ax) / $length * $half;
            imagefilledpolygon($image, array_map(fn (float $v): int => (int) round($v), [$ax + $nx, $ay + $ny, $bx + $nx, $by + $ny, $bx - $nx, $by - $ny, $ax - $nx, $ay - $ny]), $color);
        }

        foreach ($points as [$px, $py]) {
            $this->disc($image, $px, $py, $half, $color);
        }
    }

    /**
     * Concentric translucent discs approximate a linear radial falloff.
     *
     * @param  array<string, mixed>  $element
     */
    private function glow(GdImage $canvas, array $element, float $f): void
    {
        $rings = 40;
        $step = 1 - (1 - (float) $element['opacity']) ** (1 / $rings);

        // A glow is soft by nature, so it's drawn on a small layer covering
        // only the visible part of it and scaled up — drawing 40 full-size
        // discs on a supersampled story-format canvas would take seconds.
        $x1 = (int) max(0, floor(($element['cx'] - $element['r']) * $f));
        $y1 = (int) max(0, floor(($element['cy'] - $element['r']) * $f));
        $x2 = (int) min(imagesx($canvas), ceil(($element['cx'] + $element['r']) * $f));
        $y2 = (int) min(imagesy($canvas), ceil(($element['cy'] + $element['r']) * $f));

        if ($x2 <= $x1 || $y2 <= $y1) {
            return;
        }

        $down = 8;
        $layer = $this->blank((int) ceil(($x2 - $x1) / $down) + 1, (int) ceil(($y2 - $y1) / $down) + 1, true);
        imagealphablending($layer, true);
        $color = $this->color($layer, $element['color'], $step);

        for ($i = $rings; $i >= 1; $i--) {
            $this->disc($layer, ($element['cx'] * $f - $x1) / $down, ($element['cy'] * $f - $y1) / $down, $element['r'] * $f * $i / $rings / $down, $color);
        }

        imagecopyresampled($canvas, $layer, $x1, $y1, 0, 0, ($x2 - $x1), ($y2 - $y1), (int) round(($x2 - $x1) / $down), (int) round(($y2 - $y1) / $down));
    }

    private function color(GdImage $image, string $hex, float $opacity = 1): int
    {
        [$r, $g, $b] = $this->rgb($hex);

        return imagecolorallocatealpha($image, $r, $g, $b, (int) round(127 * (1 - max(0, min(1, $opacity)))));
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function rgb(string $hex): array
    {
        $hex = ltrim($hex, '#');

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }
}
