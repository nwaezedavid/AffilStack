<?php

namespace App\Services\Referrals\MarketingKit;

use InvalidArgumentException;

/**
 * Lays out one built-in banner design at one size as a BannerScene. The copy
 * and theme come from config('affiliate_kit.designs'); the layout adapts to
 * the format — a thin strip (leaderboards, mobile), a wide billboard, a box
 * (rectangles, square posts) or a tall skyscraper/story — and every line of
 * text is fitted with real font metrics, so nothing overflows at any size.
 */
class BannerDesigner
{
    /**
     * @var array<string, array<string, mixed>>
     */
    private const THEMES = [
        'navy' => [
            'bg' => ['from' => '#0a1730', 'to' => '#1d3568', 'dir' => 'h'],
            'headline' => '#ffffff', 'sub' => '#c9d4f2', 'accent' => '#e0b563', 'eyebrow' => '#e0b563',
            'cta' => ['from' => '#e8c27a', 'to' => '#c9a24a', 'dir' => 'v'], 'ctaText' => '#0b1e3d',
            'word' => '#ffffff', 'wordAccent' => '#e0b563',
            'icon' => ['from' => '#e8c27a', 'to' => '#c9a24a', 'dir' => 'v'], 'iconBars' => '#0b1e3d',
            'glowA' => ['#2452d9', 0.55], 'glowB' => ['#c9a24a', 0.22],
            'card' => '#ffffff', 'cardOpacity' => 0.97, 'cardBack' => '#ffffff', 'cardBackOpacity' => 0.12,
            'skeleton' => '#c9d4f2', 'muted' => '#ffffff', 'mutedOpacity' => 0.22, 'nodeText' => '#ffffff',
        ],
        'blue' => [
            'bg' => ['from' => '#1b3fb3', 'to' => '#0b1e3d', 'dir' => 'h'],
            'headline' => '#ffffff', 'sub' => '#dbe4ff', 'accent' => '#e0b563', 'eyebrow' => '#e0b563',
            'cta' => ['from' => '#e8c27a', 'to' => '#c9a24a', 'dir' => 'v'], 'ctaText' => '#0b1e3d',
            'word' => '#ffffff', 'wordAccent' => '#e0b563',
            'icon' => ['from' => '#e8c27a', 'to' => '#c9a24a', 'dir' => 'v'], 'iconBars' => '#0b1e3d',
            'glowA' => ['#c9a24a', 0.28], 'glowB' => ['#5b84ff', 0.35],
            'card' => '#ffffff', 'cardOpacity' => 0.97, 'cardBack' => '#ffffff', 'cardBackOpacity' => 0.14,
            'skeleton' => '#dbe4ff', 'muted' => '#ffffff', 'mutedOpacity' => 0.24, 'nodeText' => '#ffffff',
        ],
        'light' => [
            'bg' => ['from' => '#ffffff', 'to' => '#e6ecff', 'dir' => 'v'],
            'headline' => '#0b1e3d', 'sub' => '#48506b', 'accent' => '#2452d9', 'eyebrow' => '#9c7a2e',
            'cta' => ['from' => '#1d3568', 'to' => '#0b1e3d', 'dir' => 'v'], 'ctaText' => '#ffffff',
            'word' => '#0b1e3d', 'wordAccent' => '#2452d9',
            'icon' => ['from' => '#2f5fe6', 'to' => '#16328f', 'dir' => 'v'], 'iconBars' => '#ffffff',
            'glowA' => ['#2452d9', 0.16], 'glowB' => ['#e0b563', 0.28],
            'card' => '#ffffff', 'cardOpacity' => 1.0, 'cardBack' => '#2452d9', 'cardBackOpacity' => 0.12,
            'skeleton' => '#c3cdea', 'muted' => '#2452d9', 'mutedOpacity' => 0.18, 'nodeText' => '#0b1e3d',
        ],
        'brand' => [
            'bg' => ['from' => '#2f5fe6', 'to' => '#16328f', 'dir' => 'v'],
            'headline' => '#ffffff', 'sub' => '#dbe4ff', 'accent' => '#e0b563', 'eyebrow' => '#f3d48f',
            'cta' => '#ffffff', 'ctaText' => '#0b1e3d',
            'word' => '#ffffff', 'wordAccent' => '#f3d48f',
            'icon' => '#ffffff', 'iconBars' => '#2452d9',
            'glowA' => ['#ffffff', 0.18], 'glowB' => ['#e0b563', 0.3],
            'card' => '#ffffff', 'cardOpacity' => 0.97, 'cardBack' => '#ffffff', 'cardBackOpacity' => 0.16,
            'skeleton' => '#dbe4ff', 'muted' => '#ffffff', 'mutedOpacity' => 0.25, 'nodeText' => '#ffffff',
        ],
        'gold' => [
            'bg' => ['from' => '#ecc87f', 'to' => '#c9a24a', 'dir' => 'v'],
            'headline' => '#0b1e3d', 'sub' => '#2a2a33', 'accent' => '#0b1e3d', 'eyebrow' => '#142752',
            'cta' => ['from' => '#1d3568', 'to' => '#0b1e3d', 'dir' => 'v'], 'ctaText' => '#ffffff',
            'word' => '#0b1e3d', 'wordAccent' => '#1b3fb3',
            'icon' => ['from' => '#1d3568', 'to' => '#0b1e3d', 'dir' => 'v'], 'iconBars' => '#e0b563',
            'glowA' => ['#ffffff', 0.32], 'glowB' => ['#9c7a2e', 0.25],
            'card' => '#ffffff', 'cardOpacity' => 0.96, 'cardBack' => '#0b1e3d', 'cardBackOpacity' => 0.12,
            'skeleton' => '#d8cfb8', 'muted' => '#0b1e3d', 'mutedOpacity' => 0.16, 'nodeText' => '#0b1e3d',
        ],
    ];

    public function __construct(private BannerFonts $fonts) {}

    /**
     * @return array<string, mixed>
     */
    public static function theme(string $name): array
    {
        return self::THEMES[$name] ?? self::THEMES['navy'];
    }

    public function build(string $design, string $sizeKey): BannerScene
    {
        $config = config("affiliate_kit.designs.{$design}");
        $size = config("affiliate_kit.sizes.{$sizeKey}");

        if (! is_array($config) || ! is_array($size)) {
            throw new InvalidArgumentException("Unknown banner {$design}/{$sizeKey}.");
        }

        $w = (int) $size['width'];
        $h = (int) $size['height'];
        $theme = self::theme($config['theme']);
        $scene = new BannerScene($w, $h, 'AffilStack: '.$config['headline']);

        $scene->rect(0, 0, $w, $h, $theme['bg']);
        $long = max($w, $h);
        $scene->glow($w * 0.92, $h * 0.08, $long * 0.62, $theme['glowA'][0], $theme['glowA'][1]);
        $scene->glow($w * 0.04, $h * 0.98, $long * 0.5, $theme['glowB'][0], $theme['glowB'][1]);

        $ratio = $w / $h;

        match (true) {
            $h <= 100 => $this->strip($scene, $config, $theme),
            $ratio >= 1.6 => $this->wide($scene, $config, $theme),
            $ratio > 0.75 => $this->box($scene, $config, $theme),
            default => $this->tall($scene, $config, $theme),
        };

        return $scene;
    }

    /**
     * The AffilStack mark on its own (for the brand-assets tab): a square icon,
     * or icon + wordmark on a transparent background.
     */
    public function brandMark(string $variant): BannerScene
    {
        return match ($variant) {
            'icon' => tap(new BannerScene(512, 512, 'AffilStack icon'), fn (BannerScene $scene) => $this->logo($scene, 0, 0, 512, self::theme('navy'), false)),
            'wordmark-light' => tap(new BannerScene(640, 160, 'AffilStack logo'), fn (BannerScene $scene) => $this->logo($scene, 16, 32, 96, self::theme('navy'), true)),
            'wordmark-dark' => tap(new BannerScene(640, 160, 'AffilStack logo'), fn (BannerScene $scene) => $this->logo($scene, 16, 32, 96, self::theme('light'), true)),
            default => throw new InvalidArgumentException("Unknown brand mark {$variant}."),
        };
    }

    /**
     * Leaderboards and mobile banners: logo, one punchy line, button.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $theme
     */
    private function strip(BannerScene $scene, array $config, array $theme): void
    {
        $w = $scene->width;
        $h = $scene->height;

        if ($h >= 100 && $w < 500) {
            $pad = 14;
            $this->logo($scene, $pad, $pad, 22, $theme, true);
            $ctaPx = 12.0;
            [$ctaW, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);
            $textW = $w - 2 * $pad - $ctaW - 12;
            $fit = $this->fonts->fit($config['headline_short'], 'extrabold', $textW, 2, 19, 11, $h - $pad * 2 - 30, 1.1);
            $this->textBlock($scene, $pad, $pad + 32, $fit, 'extrabold', $theme['headline'], 1.1);
            $this->cta($scene, $w - $pad - $ctaW, $h - $pad - $ctaH, $ctaPx, $config['cta'], $theme);

            return;
        }

        $padX = round($h * 0.24);
        $iconSize = min(round($h * 0.5), 42);
        $logoW = $this->logo($scene, $padX, ($h - $iconSize) / 2, $iconSize, $theme, $w >= 600);
        $ctaPx = max(9.5, min(round($h * 0.17), 16));
        [$ctaW, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);
        $gap = round($h * 0.28);
        $textX = $padX + $logoW + $gap;
        $textW = $w - $textX - $padX - $ctaW - $gap;
        $maxH = $h - 2 * round($h * 0.14);

        $long = $this->fonts->fit($config['headline'], 'extrabold', $textW, 1, round($h * 0.3), 9, $maxH, 1.08);
        $short = $this->fonts->fit($config['headline_short'], 'extrabold', $textW, 2, round($h * 0.3), 9, $maxH, 1.08);
        $fits = fn (array $fit): bool => count($fit['lines']) <= 2 && max(array_map(fn (string $line): float => $this->fonts->width($line, 'extrabold', $fit['px']), $fit['lines'])) <= $textW;
        $fit = ($fits($long) && count($long['lines']) === 1 && $long['px'] >= $short['px'] * 0.9) ? $long : $short;

        $this->textBlock($scene, $textX, ($h - $fit['height']) / 2, $fit, 'extrabold', $theme['headline'], 1.08);
        $this->cta($scene, $w - $padX - $ctaW, ($h - $ctaH) / 2, $ctaPx, $config['cta'], $theme);
    }

    /**
     * Billboard and link-post formats: copy on the left, visual on the right.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $theme
     */
    private function wide(BannerScene $scene, array $config, array $theme): void
    {
        $w = $scene->width;
        $h = $scene->height;
        $pad = round($h * 0.13);
        $textW = $w * 0.55 - $pad;
        $iconSize = min(round($h * 0.13), 60);
        $eyebrowPx = max(10, round($h * 0.042));
        $headPx = min(round($h * 0.15), 72);
        $ctaPx = max(12, min(round($h * 0.058), 26));
        [, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);

        $compact = $h < 400;
        $gapS = $h * ($compact ? 0.04 : 0.05);
        $gapL = $h * ($compact ? 0.065 : 0.075);
        $sub = $compact
            ? $this->fonts->fit($config['sub_short'], 'medium', $textW, 1, max(12, round($h * 0.06)), 10, INF, 1.35)
            : $this->fonts->fit($config['sub'], 'medium', $textW, 2, round($h * 0.042), 11, INF, 1.35);
        $showEyebrow = ! $compact;
        $fixed = $iconSize + $gapL + ($showEyebrow ? $eyebrowPx + $gapS : 0) + $gapS + $sub['height'] + $gapL + $ctaH;
        $head = $this->fonts->fit($config['headline'], 'extrabold', $textW, 3, $headPx, 14, $h - 2 * $pad - $fixed, 1.1);
        $blocks = $fixed + $head['height'];

        $y = max($pad * 0.8, ($h - $blocks) / 2);
        $this->logo($scene, $pad, $y, $iconSize, $theme, true);
        $y += $iconSize + $gapL;

        if ($showEyebrow) {
            $scene->text($pad, $y + $eyebrowPx * 0.72, 'FOR AFFILIATE MARKETERS', 'semibold', $eyebrowPx, $theme['eyebrow']);
            $y += $eyebrowPx + $gapS;
        }

        $this->textBlock($scene, $pad, $y, $head, 'extrabold', $theme['headline'], 1.1);
        $y += $head['height'] + $gapS;
        $this->textBlock($scene, $pad, $y, $sub, 'medium', $theme['sub'], 1.35);
        $y += $sub['height'] + $gapL;
        $this->cta($scene, $pad, $y, $ctaPx, $config['cta'], $theme);

        $this->visual($scene, $config, $theme, $w * 0.6, $pad, $w * 0.4 - $pad, $h - 2 * $pad);
    }

    /**
     * Rectangles and the square post: stacked copy, button at the bottom,
     * visual in between when there's room for one.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $theme
     */
    private function box(BannerScene $scene, array $config, array $theme): void
    {
        $w = $scene->width;
        $h = $scene->height;
        $large = $w >= 600;
        $pad = round($w * ($large ? 0.08 : 0.07));
        $iconSize = $large ? round($w * 0.07) : max(20, round($w * 0.075));
        $innerW = $w - 2 * $pad;
        $ctaPx = $large ? round($w * 0.03) : 12.5;
        [$ctaW, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);

        $y = $pad;
        $this->logo($scene, $pad, $y, $iconSize, $theme, true);
        $y += $iconSize + ($large ? $h * 0.06 : $h * 0.065);

        if ($large) {
            $eyebrowPx = round($w * 0.022);
            $scene->text($pad, $y + $eyebrowPx * 0.72, 'FOR AFFILIATE MARKETERS', 'semibold', $eyebrowPx, $theme['eyebrow']);
            $y += $eyebrowPx + $h * 0.025;
        }

        $head = $this->fonts->fit($config['headline'], 'extrabold', $innerW, 3, $large ? round($w * 0.082) : round($w * 0.088), 14, $h * ($large ? 0.3 : 0.4), 1.1);
        $this->textBlock($scene, $pad, $y, $head, 'extrabold', $theme['headline'], 1.1);
        $y += $head['height'] + $h * ($large ? 0.03 : 0.04);

        $ctaY = $h - $pad - $ctaH;
        $subText = $large ? $config['sub'] : $config['sub_short'];
        $sub = $this->fonts->fit($subText, 'medium', $innerW, 2, $large ? round($w * 0.032) : 12.5, 10, INF, 1.35);

        if ($y + $sub['height'] <= $ctaY - $h * 0.05) {
            $this->textBlock($scene, $pad, $y, $sub, 'medium', $theme['sub'], 1.35);
            $y += $sub['height'];
        }

        $this->cta($scene, $pad, $ctaY, $ctaPx, $config['cta'], $theme);

        if ($large) {
            $this->domain($scene, $w - $pad, $ctaY + $ctaH / 2, round($w * 0.022), $theme);
        }

        $visualTop = $y + $h * 0.05;
        $visualBottom = $ctaY - $h * 0.05;

        if ($visualBottom - $visualTop >= $h * 0.22) {
            $this->visual($scene, $config, $theme, $pad, $visualTop, $innerW, $visualBottom - $visualTop);
        } elseif (! $large) {
            $this->cornerAccent($scene, $theme, $pad + $ctaW + 10, $ctaY, $w - $pad, $ctaY + $ctaH);
        }
    }

    /**
     * Skyscrapers and the story format: logo, copy, visual, button.
     *
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $theme
     */
    private function tall(BannerScene $scene, array $config, array $theme): void
    {
        $w = $scene->width;
        $h = $scene->height;
        $large = $w >= 600;
        $narrow = $w < 200;
        $pad = round($w * ($narrow ? 0.09 : 0.09));
        $innerW = $w - 2 * $pad;
        $iconSize = $large ? round($w * 0.085) : ($narrow ? 20 : 30);

        $y = $large ? $h * 0.07 : $pad + 4;
        $this->logo($scene, $pad, $y, $iconSize, $theme, true);
        $y += $iconSize + $h * ($large ? 0.06 : 0.05);

        if (! $narrow) {
            $eyebrowPx = $large ? round($w * 0.026) : 10.5;
            $scene->text($pad, $y + $eyebrowPx * 0.72, 'FOR AFFILIATE MARKETERS', 'semibold', $eyebrowPx, $theme['eyebrow']);
            $y += $eyebrowPx + $h * 0.02;
        }

        $headMax = $large ? round($w * 0.1) : ($narrow ? 21 : 34);
        $head = $this->fonts->fit($config['headline'], 'extrabold', $innerW, $narrow ? 5 : 4, $headMax, $narrow ? 13 : 16, $h * 0.3, 1.1);
        $this->textBlock($scene, $pad, $y, $head, 'extrabold', $theme['headline'], 1.1);
        $y += $head['height'] + $h * 0.025;

        $sub = $this->fonts->fit($config['sub'], 'medium', $innerW, $narrow ? 5 : 3, $large ? round($w * 0.036) : ($narrow ? 12.5 : 14.5), 10, INF, 1.38);
        $this->textBlock($scene, $pad, $y, $sub, 'medium', $theme['sub'], 1.38);
        $y += $sub['height'];

        $ctaPx = $large ? round($w * 0.036) : ($narrow ? 11.5 : 14);
        [$ctaW, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);
        while ($ctaW > $innerW && $ctaPx > 9) {
            $ctaPx -= 0.5;
            [$ctaW, $ctaH] = $this->ctaSize($config['cta'], $ctaPx);
        }

        $domainPx = $large ? round($w * 0.026) : ($narrow ? 10 : 11);
        $ctaY = $h - ($large ? $h * 0.07 : $pad) - $domainPx * 2.2 - $ctaH;
        $this->cta($scene, ($w - $ctaW) / 2, $ctaY, $ctaPx, $config['cta'], $theme);
        $this->domain($scene, $w / 2, $ctaY + $ctaH + $domainPx * 1.6, $domainPx, $theme, 'center');

        $visualTop = $y + $h * 0.05;
        $visualBottom = $ctaY - $h * 0.05;

        if ($visualBottom - $visualTop >= $h * 0.12) {
            $this->visual($scene, $config, $theme, $pad, $visualTop, $innerW, $visualBottom - $visualTop);
        }
    }

    /**
     * The AffilStack mark: a rounded-square icon with three stacked bars,
     * optionally followed by the "AffilStack" wordmark. Returns its width.
     *
     * @param  array<string, mixed>  $theme
     */
    private function logo(BannerScene $scene, float $x, float $y, float $size, array $theme, bool $withWordmark): float
    {
        $scene->rect($x, $y, $size, $size, $theme['icon'], $size * 0.24);

        foreach ([[0.25, 0.4], [0.43, 0.54], [0.61, 0.68]] as [$top, $width]) {
            $barW = $size * $width;
            $scene->rect($x + ($size - $barW) / 2, $y + $size * $top, $barW, $size * 0.13, $theme['iconBars'], $size * 0.065);
        }

        if (! $withWordmark) {
            return $size;
        }

        $px = $size * 0.6;
        $wordX = $x + $size + $size * 0.26;
        $baseline = $y + $size / 2 + $px * 0.36;
        $affilW = $this->fonts->width('Affil', 'extrabold', $px);
        $scene->text($wordX, $baseline, 'Affil', 'extrabold', $px, $theme['word']);
        $scene->text($wordX + $affilW + $px * 0.04, $baseline, 'Stack', 'extrabold', $px, $theme['wordAccent']);

        return $wordX + $affilW + $px * 0.04 + $this->fonts->width('Stack', 'extrabold', $px) - $x;
    }

    /**
     * @return array{0: float, 1: float}
     */
    private function ctaSize(string $label, float $px): array
    {
        return [$this->fonts->width($label.'  →', 'semibold', $px) + $px * 2.3, $px * 2.5];
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function cta(BannerScene $scene, float $x, float $y, float $px, string $label, array $theme): void
    {
        [$w, $h] = $this->ctaSize($label, $px);
        $scene->rect($x, $y + $px * 0.18, $w, $h, '#000000', $h / 2, 0.14);
        $scene->rect($x, $y, $w, $h, $theme['cta'], $h / 2);
        $scene->text($x + $px * 1.15, $y + $h / 2 + $px * 0.36, $label.'  →', 'semibold', $px, $theme['ctaText']);
    }

    /**
     * @param  array<string, mixed>  $theme
     */
    private function domain(BannerScene $scene, float $x, float $middle, float $px, array $theme, string $align = 'right'): void
    {
        $host = preg_replace('/^www\./', '', (string) parse_url((string) config('app.url'), PHP_URL_HOST)) ?: 'affilstack.com';
        $width = $this->fonts->width($host, 'semibold', $px);
        $left = match ($align) {
            'center' => $x - $width / 2,
            default => $x - $width,
        };
        $scene->text($left, $middle + $px * 0.36, $host, 'semibold', $px, $theme['sub'], 0.85);
    }

    /**
     * @param  array{px: float, lines: array<int, string>, height: float}  $fit
     */
    private function textBlock(BannerScene $scene, float $x, float $top, array $fit, string $weight, string $color, float $lineHeight): void
    {
        foreach ($fit['lines'] as $i => $line) {
            $scene->text($x, $top + $fit['px'] * 0.72 + $i * $fit['px'] * $lineHeight, $line, $weight, $fit['px'], $color);
        }
    }

    /**
     * A small decorative cluster for rectangles too short to hold a visual.
     *
     * @param  array<string, mixed>  $theme
     */
    private function cornerAccent(BannerScene $scene, array $theme, float $x1, float $y1, float $x2, float $y2): void
    {
        $r = min(($y2 - $y1) / 2, 14);
        $cx = $x2 - $r;
        $cy = ($y1 + $y2) / 2;

        if ($cx - $r < $x1) {
            return;
        }

        $scene->circle($cx, $cy, $r, $theme['accent'], 0.9);
        $scene->line([[$cx - $r * 0.42, $cy + $r * 0.02], [$cx - $r * 0.08, $cy + $r * 0.36], [$cx + $r * 0.45, $cy - $r * 0.32]], $r * 0.22, $theme['ctaText'] === '#ffffff' ? '#ffffff' : '#0b1e3d');
        $scene->circle($cx - $r * 2.6, $cy, $r * 0.32, $theme['accent'], 0.45);
        $scene->circle($cx - $r * 3.7, $cy, $r * 0.22, $theme['accent'], 0.3);
    }

    /**
     * @param  array<string, mixed>  $config
     * @param  array<string, mixed>  $theme
     */
    private function visual(BannerScene $scene, array $config, array $theme, float $x, float $y, float $w, float $h): void
    {
        match ($config['visual']) {
            'bars' => $this->bars($scene, $theme, $x, $y, $w, $h),
            'search' => $this->search($scene, $theme, $x, $y, $w, $h),
            'flow' => $this->flow($scene, $theme, $x, $y, $w, $h),
            'checklist' => $this->checklist($scene, $theme, $x, $y, $w, $h),
            default => $this->stack($scene, $theme, $x, $y, $w, $h),
        };
    }

    /**
     * Three stacked dashboard cards — "the whole stack in one place".
     *
     * @param  array<string, mixed>  $theme
     */
    private function stack(BannerScene $scene, array $theme, float $x, float $y, float $w, float $h): void
    {
        $cw = min($w * 0.86, $h * 1.35);
        $ch = $cw * 0.62;
        $step = $ch * 0.16;
        $cx = $x + ($w - $cw) / 2;
        $cy = $y + ($h - $ch - 2 * $step) / 2 + 2 * $step;
        $r = $cw * 0.06;

        $scene->rect($cx + $cw * 0.12, $cy - 2 * $step, $cw * 0.76, $ch, $theme['cardBack'], $r, $theme['cardBackOpacity'] * 0.8);
        $scene->rect($cx + $cw * 0.06, $cy - $step, $cw * 0.88, $ch, $theme['cardBack'], $r, $theme['cardBackOpacity'] * 1.4);
        $scene->rect($cx, $cy + $ch * 0.04, $cw, $ch, '#000000', $r, 0.12);
        $scene->rect($cx, $cy, $cw, $ch, $theme['card'], $r, $theme['cardOpacity']);

        $p = $cw * 0.08;
        $avatar = $ch * 0.13;
        $scene->circle($cx + $p + $avatar, $cy + $p + $avatar, $avatar, '#e0b563');
        $scene->rect($cx + $p + $avatar * 2.6, $cy + $p + $avatar * 0.45, $cw * 0.38, $ch * 0.07, '#0b1e3d', $ch * 0.035, 0.85);
        $scene->rect($cx + $p + $avatar * 2.6, $cy + $p + $avatar * 1.2, $cw * 0.26, $ch * 0.055, $theme['skeleton'], $ch * 0.03);

        $barY = $cy + $ch * 0.5;
        foreach ([[0.84, '#2452d9'], [0.62, '#e0b563'], [0.44, '#2452d9']] as $i => [$fraction, $color]) {
            $rowY = $barY + $i * $ch * 0.14;
            $scene->rect($cx + $p, $rowY, $cw - 2 * $p, $ch * 0.07, $theme['skeleton'], $ch * 0.035, 0.6);
            $scene->rect($cx + $p, $rowY, ($cw - 2 * $p) * $fraction, $ch * 0.07, $color, $ch * 0.035);
        }

        $badge = $ch * 0.17;
        $bx = $cx + $cw - $badge * 0.4;
        $by = $cy + $badge * 0.2;
        $scene->circle($bx, $by, $badge, '#16a34a');
        $scene->line([[$bx - $badge * 0.42, $by + $badge * 0.02], [$bx - $badge * 0.1, $by + $badge * 0.34], [$bx + $badge * 0.45, $by - $badge * 0.3]], $badge * 0.22, '#ffffff');
    }

    /**
     * A rising bar chart with a trend arrow — "earn more".
     *
     * @param  array<string, mixed>  $theme
     */
    private function bars(BannerScene $scene, array $theme, float $x, float $y, float $w, float $h): void
    {
        $cw = min($w, $h * 1.4);
        $chH = min($h, $cw * 0.85);
        $left = $x + ($w - $cw) / 2;
        $bottom = $y + ($h + $chH) / 2;
        $heights = [0.28, 0.42, 0.5, 0.68, 0.9];
        $gap = $cw * 0.05;
        $barW = ($cw - $gap * 4) / 5;
        $tops = [];

        $scene->rect($left - $cw * 0.04, $bottom, $cw * 1.08, max(2, $chH * 0.012), $theme['muted'], 0, $theme['mutedOpacity'] * 1.8);

        foreach ($heights as $i => $fraction) {
            $bh = $chH * $fraction * 0.86;
            $bx = $left + $i * ($barW + $gap);
            $last = $i === count($heights) - 1;
            $scene->rect($bx, $bottom - $bh, $barW, $bh, $last ? ['from' => '#f3d48f', 'to' => '#c9a24a', 'dir' => 'v'] : $theme['muted'], $barW * 0.18, $last ? 1 : $theme['mutedOpacity'] + $i * 0.05);
            $tops[] = [$bx + $barW / 2, $bottom - $bh - $chH * 0.08];
        }

        $stroke = max(2.5, $chH * 0.03);
        $scene->line($tops, $stroke, $theme['accent'] === '#0b1e3d' ? '#0b1e3d' : '#ffffff');
        [$ex, $ey] = $tops[count($tops) - 1];
        [$px, $py] = $tops[count($tops) - 2];
        $angle = atan2($ey - $py, $ex - $px);
        $size = $stroke * 3.2;
        $tip = [$ex + cos($angle) * $size * 0.6, $ey + sin($angle) * $size * 0.6];
        $scene->polygon([
            $tip,
            [$ex + cos($angle + 2.5) * $size * 0.7, $ey + sin($angle + 2.5) * $size * 0.7],
            [$ex + cos($angle - 2.5) * $size * 0.7, $ey + sin($angle - 2.5) * $size * 0.7],
        ], $theme['accent'] === '#0b1e3d' ? '#0b1e3d' : '#ffffff');
        $scene->circle($tops[0][0], $tops[0][1], $stroke * 1.1, $theme['accent'] === '#0b1e3d' ? '#0b1e3d' : '#ffffff');
    }

    /**
     * A product card under a magnifying glass — "research".
     *
     * @param  array<string, mixed>  $theme
     */
    private function search(BannerScene $scene, array $theme, float $x, float $y, float $w, float $h): void
    {
        $cw = min($w * 0.78, $h * 1.15);
        $ch = $cw * 0.78;
        $cx = $x + ($w - $cw) / 2 - $cw * 0.06;
        $cy = $y + ($h - $ch) / 2 + $ch * 0.06;
        $r = $cw * 0.06;
        $p = $cw * 0.08;

        $scene->rect($cx + $cw * 0.07, $cy - $ch * 0.09, $cw, $ch, $theme['cardBack'], $r, $theme['cardBackOpacity']);
        $scene->rect($cx, $cy + $ch * 0.03, $cw, $ch, '#0b1e3d', $r, 0.12);
        $scene->rect($cx, $cy, $cw, $ch, $theme['card'], $r, $theme['cardOpacity']);
        $scene->rect($cx + $p, $cy + $p, $cw * 0.34, $ch * 0.42, ['from' => '#2f5fe6', 'to' => '#16328f', 'dir' => 'v'], $r * 0.7);
        $scene->circle($cx + $p + $cw * 0.17, $cy + $p + $ch * 0.21, $ch * 0.08, '#e0b563');
        $tx = $cx + $p + $cw * 0.42;
        $scene->rect($tx, $cy + $p + $ch * 0.02, $cw * 0.38, $ch * 0.07, '#0b1e3d', $ch * 0.035, 0.85);
        $scene->rect($tx, $cy + $p + $ch * 0.15, $cw * 0.3, $ch * 0.055, $theme['skeleton'], $ch * 0.03);
        $scene->rect($tx, $cy + $p + $ch * 0.26, $cw * 0.24, $ch * 0.055, $theme['skeleton'], $ch * 0.03);
        foreach ([0, 1] as $i) {
            $rowY = $cy + $ch * 0.66 + $i * $ch * 0.13;
            $scene->rect($cx + $p, $rowY, $cw - 2 * $p, $ch * 0.065, $theme['skeleton'], $ch * 0.033, 0.55);
            $scene->rect($cx + $p, $rowY, ($cw - 2 * $p) * ($i === 0 ? 0.9 : 0.65), $ch * 0.065, $i === 0 ? '#16a34a' : '#e0b563', $ch * 0.033);
        }

        $glassR = $cw * 0.2;
        $gx = $cx + $cw * 0.86;
        $gy = $cy + $ch * 0.12;
        $handle = $glassR * 1.05;
        $scene->line([[$gx + $glassR * 0.72, $gy + $glassR * 0.72], [$gx + $glassR * 0.72 + $handle * 0.7, $gy + $glassR * 0.72 + $handle * 0.7]], $glassR * 0.3, '#0b1e3d');
        $scene->circle($gx, $gy, $glassR, '#ffffff', 0.35);
        $scene->ring($gx, $gy, $glassR, $glassR * 0.22, $theme['accent'] === '#2452d9' ? '#2452d9' : '#e0b563');
    }

    /**
     * Four numbered steps joined by a line — "research → buyers → content → earn".
     *
     * @param  array<string, mixed>  $theme
     */
    private function flow(BannerScene $scene, array $theme, float $x, float $y, float $w, float $h): void
    {
        $steps = config('affiliate_kit.flow_steps');
        $n = count($steps);
        $horizontal = $w >= $h * 1.2;

        if ($horizontal) {
            $r = min($w / ($n * 2.8), $h * 0.2);
            $labelPx = max(10, min($r * 0.62, $w / $n * 0.17));
            $cy = $y + $h / 2 - $labelPx;
            $span = $w - 2 * $r - $w * 0.06;
            $centres = array_map(fn (int $i): array => [$x + $w * 0.03 + $r + $span * $i / ($n - 1), $cy], range(0, $n - 1));
        } else {
            $r = min($h / ($n * 3), $w * 0.16);
            $labelPx = max(10, min($r * 0.72, $w * 0.11));
            $cx = $x + $r + $w * 0.04;
            $span = $h - 2 * $r - $h * 0.04;
            $centres = array_map(fn (int $i): array => [$cx, $y + $h * 0.02 + $r + $span * $i / ($n - 1)], range(0, $n - 1));
        }

        $scene->line($centres, max(2, $r * 0.12), $theme['muted'], min(1, $theme['mutedOpacity'] * 2.2));

        foreach ($centres as $i => [$cx, $cy]) {
            $last = $i === $n - 1;
            $scene->circle($cx, $cy, $r * 1.22, $last ? '#e0b563' : $theme['muted'], $last ? 0.3 : $theme['mutedOpacity'] * 0.6);
            $scene->circle($cx, $cy, $r, $last ? '#e0b563' : '#ffffff', $last ? 1 : 0.96);
            $numPx = $r * 0.95;
            $num = (string) ($i + 1);
            $scene->text($cx - $this->fonts->width($num, 'extrabold', $numPx) / 2, $cy + $numPx * 0.36, $num, 'extrabold', $numPx, '#0b1e3d');

            $label = $steps[$i];
            if ($horizontal) {
                $px = $labelPx;
                while ($this->fonts->width($label, 'semibold', $px) > $w / $n * 0.98 && $px > 8) {
                    $px -= 0.5;
                }
                $scene->text($cx - $this->fonts->width($label, 'semibold', $px) / 2, $cy + $r * 1.22 + $px * 1.5, $label, 'semibold', $px, $theme['nodeText']);
            } else {
                $px = $labelPx;
                $maxW = $x + $w - ($cx + $r * 1.5);
                while ($this->fonts->width($label, 'semibold', $px) > $maxW && $px > 8) {
                    $px -= 0.5;
                }
                $scene->text($cx + $r * 1.55, $cy + $px * 0.36, $label, 'semibold', $px, $theme['nodeText']);
            }
        }
    }

    /**
     * A ticked feature list in a card — "replaces your whole stack".
     *
     * @param  array<string, mixed>  $theme
     */
    private function checklist(BannerScene $scene, array $theme, float $x, float $y, float $w, float $h): void
    {
        $items = config('affiliate_kit.checklist_items');
        $n = count($items);
        $cardW = min($w, $h * 2.2);
        $rowH = min($h / ($n + 0.6), $cardW * 0.16);
        $cardH = $rowH * ($n + 0.6);
        $cx = $x + ($w - $cardW) / 2;
        $cy = $y + ($h - $cardH) / 2;
        $pad = $rowH * 0.45;

        $scene->rect($cx, $cy + $rowH * 0.08, $cardW, $cardH, '#0b1e3d', $rowH * 0.3, 0.12);
        $scene->rect($cx, $cy, $cardW, $cardH, $theme['card'], $rowH * 0.3, $theme['cardOpacity']);

        $dot = $rowH * 0.24;
        $px = $rowH * 0.36;
        $maxLabel = $cardW - 2 * $pad - $dot * 3;
        foreach ($items as $label) {
            while ($this->fonts->width($label, 'semibold', $px) > $maxLabel && $px > 8) {
                $px -= 0.5;
            }
        }

        foreach ($items as $i => $label) {
            $mid = $cy + $rowH * 0.3 + $rowH * ($i + 0.5);
            $dx = $cx + $pad + $dot;
            $scene->circle($dx, $mid, $dot, $i % 2 === 0 ? '#16a34a' : '#2452d9');
            $scene->line([[$dx - $dot * 0.42, $mid + $dot * 0.02], [$dx - $dot * 0.1, $mid + $dot * 0.34], [$dx + $dot * 0.45, $mid - $dot * 0.3]], $dot * 0.24, '#ffffff');
            $scene->text($dx + $dot * 2, $mid + $px * 0.36, $label, 'semibold', $px, '#0b1e3d');
        }
    }
}
