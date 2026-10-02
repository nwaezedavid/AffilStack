<?php

namespace App\Services\Referrals\MarketingKit;

/**
 * Draws a BannerScene as a self-contained SVG: no external references at
 * all (an SVG shown through <img> can't load any), with the Latin subset of
 * each Onest weight it uses embedded as a data: URI. Every text line also
 * carries textLength set to the width it was laid out at, so even a viewer
 * that ignores embedded fonts keeps the copy inside its box.
 */
class SvgBannerRenderer
{
    public function __construct(private BannerFonts $fonts) {}

    public function render(BannerScene $scene): string
    {
        $defs = [];
        $body = [];
        $gradientIndex = 0;

        foreach ($scene->shapes() as $element) {
            $body[] = $this->shape($element, $defs, $gradientIndex);
        }

        $weights = [];
        foreach ($scene->texts() as $element) {
            $weights[$element['weight']] = true;
            $body[] = $this->text($element);
        }

        $style = '';
        foreach (array_keys($weights) as $weight) {
            $woff = BannerFonts::woffPath($weight);
            if (is_file($woff)) {
                $style .= sprintf("@font-face{font-family:'AS Onest';font-weight:%d;src:url(data:font/woff;base64,%s) format('woff');}", BannerFonts::cssWeight($weight), base64_encode((string) file_get_contents($woff)));
            }
        }

        $title = htmlspecialchars($scene->title, ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .sprintf('<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="%s">', $scene->width, $scene->height, $scene->width, $scene->height, $title)
            .'<title>'.$title.'</title>'
            .($style !== '' ? '<style>'.$style.'</style>' : '')
            .($defs !== [] ? '<defs>'.implode('', $defs).'</defs>' : '')
            .implode('', $body)
            .'</svg>';
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<int, string>  $defs
     */
    private function shape(array $element, array &$defs, int &$gradientIndex): string
    {
        $opacity = $element['opacity'] < 1 ? sprintf(' opacity="%s"', $this->n($element['opacity'])) : '';

        return match ($element['type']) {
            'rect' => sprintf('<rect x="%s" y="%s" width="%s" height="%s"%s fill="%s"%s/>',
                $this->n($element['x']), $this->n($element['y']), $this->n($element['w']), $this->n($element['h']),
                $element['r'] > 0 ? sprintf(' rx="%s"', $this->n($element['r'])) : '',
                $this->fill($element['fill'], $defs, $gradientIndex), $opacity),
            'circle' => sprintf('<circle cx="%s" cy="%s" r="%s" fill="%s"%s/>',
                $this->n($element['cx']), $this->n($element['cy']), $this->n($element['r']), $element['fill'], $opacity),
            'ring' => sprintf('<circle cx="%s" cy="%s" r="%s" fill="none" stroke="%s" stroke-width="%s"%s/>',
                $this->n($element['cx']), $this->n($element['cy']), $this->n($element['r'] - $element['width'] / 2), $element['color'], $this->n($element['width']), $opacity),
            'glow' => $this->glow($element, $defs, $gradientIndex),
            'polygon' => sprintf('<polygon points="%s" fill="%s"%s/>', $this->points($element['points']), $element['fill'], $opacity),
            'line' => sprintf('<polyline points="%s" fill="none" stroke="%s" stroke-width="%s" stroke-linecap="round" stroke-linejoin="round"%s/>',
                $this->points($element['points']), $element['color'], $this->n($element['width']), $opacity),
            default => '',
        };
    }

    /**
     * @param  array<string, mixed>  $element
     */
    private function text(array $element): string
    {
        $width = $this->fonts->width($element['text'], $element['weight'], $element['size']);

        return sprintf('<text x="%s" y="%s" font-family="\'AS Onest\', Onest, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif" font-weight="%d" font-size="%s" fill="%s"%s textLength="%s" lengthAdjust="spacingAndGlyphs">%s</text>',
            $this->n($element['x']), $this->n($element['y']), BannerFonts::cssWeight($element['weight']), $this->n($element['size']), $element['color'],
            $element['opacity'] < 1 ? sprintf(' fill-opacity="%s"', $this->n($element['opacity'])) : '',
            $this->n($width), htmlspecialchars($element['text'], ENT_XML1 | ENT_QUOTES, 'UTF-8'));
    }

    /**
     * @param  string|array{from: string, to: string, dir: string}  $fill
     * @param  array<int, string>  $defs
     */
    private function fill(string|array $fill, array &$defs, int &$gradientIndex): string
    {
        if (is_string($fill)) {
            return $fill;
        }

        $id = 'g'.(++$gradientIndex);
        $end = $fill['dir'] === 'h' ? 'x2="1" y2="0"' : 'x2="0" y2="1"';
        $defs[] = sprintf('<linearGradient id="%s" x1="0" y1="0" %s><stop offset="0" stop-color="%s"/><stop offset="1" stop-color="%s"/></linearGradient>', $id, $end, $fill['from'], $fill['to']);

        return "url(#{$id})";
    }

    /**
     * @param  array<string, mixed>  $element
     * @param  array<int, string>  $defs
     */
    private function glow(array $element, array &$defs, int &$gradientIndex): string
    {
        $id = 'g'.(++$gradientIndex);
        $defs[] = sprintf('<radialGradient id="%s"><stop offset="0" stop-color="%s" stop-opacity="%s"/><stop offset="1" stop-color="%s" stop-opacity="0"/></radialGradient>', $id, $element['color'], $this->n($element['opacity']), $element['color']);

        return sprintf('<circle cx="%s" cy="%s" r="%s" fill="url(#%s)"/>', $this->n($element['cx']), $this->n($element['cy']), $this->n($element['r']), $id);
    }

    /**
     * @param  array<int, array{0: float, 1: float}>  $points
     */
    private function points(array $points): string
    {
        return implode(' ', array_map(fn (array $point): string => $this->n($point[0]).','.$this->n($point[1]), $points));
    }

    private function n(float|int $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.') ?: '0';
    }
}
