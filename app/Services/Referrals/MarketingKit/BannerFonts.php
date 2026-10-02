<?php

namespace App\Services\Referrals\MarketingKit;

/**
 * The one typeface every built-in banner uses: Onest (SIL OFL 1.1, bundled in
 * resources/fonts/onest). Text is measured with GD/FreeType against the very
 * same TTF that PngBannerRenderer draws with, and SvgBannerRenderer embeds a
 * Latin subset of that font — so a line that fits in the PNG fits in the SVG
 * too, on any machine, with or without Onest installed.
 */
class BannerFonts
{
    /**
     * @var array<string, array{ttf: string, woff: string, weight: int}>
     */
    public const WEIGHTS = [
        'extrabold' => ['ttf' => 'Onest-ExtraBold.ttf', 'woff' => 'Onest-ExtraBold.subset.woff', 'weight' => 800],
        'semibold' => ['ttf' => 'Onest-SemiBold.ttf', 'woff' => 'Onest-SemiBold.subset.woff', 'weight' => 600],
        'medium' => ['ttf' => 'Onest-Medium.ttf', 'woff' => 'Onest-Medium.subset.woff', 'weight' => 500],
    ];

    /**
     * GD sizes text in points at 96 DPI, so a CSS pixel size converts by 3/4.
     */
    public const POINTS_PER_PIXEL = 0.75;

    /**
     * @var array<string, float>
     */
    private array $widthCache = [];

    public static function path(string $weight): string
    {
        return resource_path('fonts/onest/'.self::WEIGHTS[$weight]['ttf']);
    }

    public static function woffPath(string $weight): string
    {
        return resource_path('fonts/onest/'.self::WEIGHTS[$weight]['woff']);
    }

    public static function cssWeight(string $weight): int
    {
        return self::WEIGHTS[$weight]['weight'];
    }

    /**
     * Whether text can be measured/drawn with real TrueType metrics. Without
     * FreeType a crude width estimate keeps SVG layout working, and the PNG
     * renderer reports itself unavailable instead of drawing bitmap text.
     */
    public static function available(): bool
    {
        return function_exists('imagettfbbox') && is_file(self::path('extrabold'));
    }

    public function width(string $text, string $weight, float $px): float
    {
        $key = $weight.'|'.$px.'|'.$text;

        if (isset($this->widthCache[$key])) {
            return $this->widthCache[$key];
        }

        if (! self::available()) {
            return $this->widthCache[$key] = mb_strlen($text) * $px * 0.56;
        }

        $box = imagettfbbox($px * self::POINTS_PER_PIXEL, 0, self::path($weight), $text);

        return $this->widthCache[$key] = (float) max($box[2], $box[4]) - min($box[0], $box[6]);
    }

    /**
     * Greedy word wrap at a fixed size.
     *
     * @return array<int, string>
     */
    public function wrap(string $text, string $weight, float $px, float $maxWidth): array
    {
        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) as $word) {
            $candidate = $current === '' ? $word : $current.' '.$word;

            if ($current !== '' && $this->width($candidate, $weight, $px) > $maxWidth) {
                $lines[] = $current;
                $current = $word;
            } else {
                $current = $candidate;
            }
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    /**
     * Largest size (stepping down from $maxPx) at which $text wraps into at
     * most $maxLines lines no wider than $maxWidth and no taller than
     * $maxHeight.
     *
     * @return array{px: float, lines: array<int, string>, height: float}
     */
    public function fit(string $text, string $weight, float $maxWidth, int $maxLines, float $maxPx, float $minPx, float $maxHeight = INF, float $lineHeight = 1.15): array
    {
        $px = $maxPx;

        while (true) {
            $lines = $this->wrap($text, $weight, $px, $maxWidth);
            $height = self::blockHeight(count($lines), $px, $lineHeight);
            $widest = max(array_map(fn (string $line): float => $this->width($line, $weight, $px), $lines));

            if ((count($lines) <= $maxLines && $height <= $maxHeight && $widest <= $maxWidth) || $px <= $minPx) {
                return ['px' => $px, 'lines' => $this->balance($text, $weight, $px, $maxWidth, $lines), 'height' => $height];
            }

            $px = max($minPx, $px - max(0.5, $px * 0.04));
        }
    }

    /**
     * Re-wraps at the narrowest width that keeps the same number of lines
     * (like CSS text-wrap: balance), so a headline never ends on a lonely
     * one-word line.
     *
     * @param  array<int, string>  $lines
     * @return array<int, string>
     */
    private function balance(string $text, string $weight, float $px, float $maxWidth, array $lines): array
    {
        $count = count($lines);

        if ($count < 2) {
            return $lines;
        }

        $low = $maxWidth * 0.5;
        $high = $maxWidth;

        for ($i = 0; $i < 12; $i++) {
            $mid = ($low + $high) / 2;
            count($this->wrap($text, $weight, $px, $mid)) <= $count ? $high = $mid : $low = $mid;
        }

        return $this->wrap($text, $weight, $px, $high);
    }

    /**
     * Visual height of a text block: cap height of the first line, plus one
     * line-height per extra line, plus room for descenders.
     */
    public static function blockHeight(int $lineCount, float $px, float $lineHeight): float
    {
        return 0.72 * $px + max(0, $lineCount - 1) * $px * $lineHeight + 0.2 * $px;
    }
}
