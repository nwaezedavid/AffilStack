<?php

namespace App\Services\Referrals\MarketingKit;

/**
 * A renderer-agnostic drawing of one banner: a flat list of simple shapes
 * and text lines in CSS-pixel coordinates. BannerDesigner builds it once;
 * SvgBannerRenderer and PngBannerRenderer each draw the exact same list, so
 * the SVG and PNG versions of a banner can never drift apart.
 *
 * A fill is either a "#rrggbb" string or a two-stop linear gradient
 * ['from' => '#…', 'to' => '#…', 'dir' => 'v'|'h'].
 *
 * Text is always painted after every shape (both renderers do this), so a
 * design never needs to worry about a shape covering a label.
 */
class BannerScene
{
    /**
     * @var array<int, array<string, mixed>>
     */
    public array $elements = [];

    public function __construct(public int $width, public int $height, public string $title = 'AffilStack') {}

    /**
     * @param  string|array{from: string, to: string, dir: string}  $fill
     */
    public function rect(float $x, float $y, float $w, float $h, string|array $fill, float $radius = 0, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'rect', 'x' => $x, 'y' => $y, 'w' => $w, 'h' => $h, 'r' => min($radius, $w / 2, $h / 2), 'fill' => $fill, 'opacity' => $opacity];

        return $this;
    }

    public function circle(float $cx, float $cy, float $r, string $fill, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'circle', 'cx' => $cx, 'cy' => $cy, 'r' => $r, 'fill' => $fill, 'opacity' => $opacity];

        return $this;
    }

    public function ring(float $cx, float $cy, float $r, float $width, string $color, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'ring', 'cx' => $cx, 'cy' => $cy, 'r' => $r, 'width' => $width, 'color' => $color, 'opacity' => $opacity];

        return $this;
    }

    /**
     * A soft radial glow fading from $opacity at the centre to nothing at $r.
     */
    public function glow(float $cx, float $cy, float $r, string $color, float $opacity): static
    {
        $this->elements[] = ['type' => 'glow', 'cx' => $cx, 'cy' => $cy, 'r' => $r, 'color' => $color, 'opacity' => $opacity];

        return $this;
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function polygon(array $points, string $fill, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'polygon', 'points' => $points, 'fill' => $fill, 'opacity' => $opacity];

        return $this;
    }

    /**
     * An open polyline with round caps and joins.
     *
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    public function line(array $points, float $width, string $color, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'line', 'points' => $points, 'width' => $width, 'color' => $color, 'opacity' => $opacity];

        return $this;
    }

    /**
     * One line of text, left-aligned at $x with its baseline at $y.
     */
    public function text(float $x, float $baseline, string $text, string $weight, float $size, string $color, float $opacity = 1): static
    {
        $this->elements[] = ['type' => 'text', 'x' => $x, 'y' => $baseline, 'text' => $text, 'weight' => $weight, 'size' => $size, 'color' => $color, 'opacity' => $opacity];

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function shapes(): array
    {
        return array_values(array_filter($this->elements, fn (array $element): bool => $element['type'] !== 'text'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function texts(): array
    {
        return array_values(array_filter($this->elements, fn (array $element): bool => $element['type'] === 'text'));
    }

    /**
     * A stable fingerprint of what this scene draws — the PNG cache key and
     * the ETag of both image formats.
     */
    public function fingerprint(): string
    {
        return substr(sha1(json_encode([$this->width, $this->height, $this->elements, PngBannerRenderer::VERSION])), 0, 16);
    }
}
