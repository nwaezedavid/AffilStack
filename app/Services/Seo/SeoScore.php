<?php

namespace App\Services\Seo;

use App\Support\Seo\SeoInput;
use Illuminate\Support\Str;

/**
 * The RankMath-style on-page checklist and 0–100 score shown live in the
 * SEO editors and in the SEO > Page SEO list. Purely advisory — nothing is
 * blocked from saving.
 *
 * Each check is pass (full weight), warn (half) or fail (none); checks that
 * can't apply (no content to scan, no focus keyword yet) are left out of
 * the total rather than counted against the page.
 */
class SeoScore
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESCRIPTION_MIN = 120;

    public const DESCRIPTION_MAX = 160;

    /**
     * @param  array{title?: ?string, description?: ?string, content?: ?string, url?: ?string, image?: array{width: ?int, height: ?int}|null, image_is_default?: bool}  $resolved
     *                                                                                                                                                                             the effective values (after fallbacks) the page actually renders
     * @return array{score: int, checks: array<int, array{label: string, status: string, hint: string}>}
     */
    public function evaluate(SeoInput $input, array $resolved): array
    {
        $title = trim((string) ($resolved['title'] ?? ''));
        $description = trim((string) ($resolved['description'] ?? ''));
        $content = isset($resolved['content']) ? trim((string) $resolved['content']) : null;
        $url = (string) ($resolved['url'] ?? '');
        $keyword = $input->focusKeyword;
        $checks = [];

        $titleLength = mb_strlen($title);
        $checks[] = $this->check(
            15,
            $titleLength >= self::TITLE_MIN && $titleLength <= self::TITLE_MAX ? 'pass' : ($titleLength > 0 && $titleLength <= 70 ? 'warn' : 'fail'),
            "SEO title is {$titleLength} characters (aim for ".self::TITLE_MIN.'–'.self::TITLE_MAX.')',
            'Google cuts titles off at roughly 60 characters (about 580px).',
        );

        $descriptionLength = mb_strlen($description);
        $checks[] = $this->check(
            15,
            $descriptionLength >= self::DESCRIPTION_MIN && $descriptionLength <= self::DESCRIPTION_MAX ? 'pass' : ($descriptionLength >= 50 && $descriptionLength <= 200 ? 'warn' : 'fail'),
            "Meta description is {$descriptionLength} characters (aim for ".self::DESCRIPTION_MIN.'–'.self::DESCRIPTION_MAX.')',
            'The snippet under your title in search results; longer text is truncated.',
        );

        $checks[] = $this->check(10, $keyword ? 'pass' : 'fail', 'Focus keyword is set', 'The one phrase this page should rank for.');

        if ($keyword) {
            $checks[] = $this->check(10, $this->contains($title, $keyword) ? 'pass' : 'fail', 'Focus keyword appears in the SEO title', 'Put it near the start of the title if it reads naturally.');
            $checks[] = $this->check(10, $this->contains($description, $keyword) ? 'pass' : 'fail', 'Focus keyword appears in the meta description', 'Google bolds matching words in the snippet.');

            if ($content !== null && $content !== '') {
                $checks[] = $this->check(10, $this->contains($content, $keyword) ? 'pass' : 'fail', 'Focus keyword appears in the page content', 'Use it naturally in the copy, ideally in a heading.');
            }

            if ($url !== '') {
                $checks[] = $this->check(5, $this->contains(str_replace(['-', '_', '/'], ' ', (string) parse_url($url, PHP_URL_PATH)), $keyword) || $this->isHomepage($url) ? 'pass' : 'warn', 'Focus keyword appears in the URL', 'Short, keyword-rich URLs help a little. (Changing a live URL needs a redirect.)');
            }
        }

        $secondary = $input->secondaryKeywords;
        $checks[] = $this->check(5, $secondary !== [] ? 'pass' : 'warn', 'Secondary keywords are set ('.count($secondary).')', 'Related phrases and synonyms people also search for.');

        if ($secondary !== []) {
            $haystack = $title.' '.$description.' '.($content ?? '');
            $found = count(array_filter($secondary, fn (string $word) => $this->contains($haystack, $word)));
            $checks[] = $this->check(
                5,
                $found === count($secondary) ? 'pass' : ($found > 0 ? 'warn' : 'fail'),
                "{$found} of ".count($secondary).' secondary keywords appear on the page',
                'Work each one into the title, description or copy at least once.',
            );
        }

        $image = $resolved['image'] ?? null;
        $isDefault = (bool) ($resolved['image_is_default'] ?? false);
        $checks[] = $this->check(
            10,
            $image ? ($isDefault ? 'warn' : 'pass') : 'fail',
            $image ? ($isDefault ? 'Social image: using the sitewide default' : 'Social image is set for this page') : 'No social share image',
            'A 1200×630 image makes links stand out on Facebook, LinkedIn, X and WhatsApp.',
        );

        if ($image && ($image['width'] ?? null)) {
            $width = (int) $image['width'];
            $height = (int) $image['height'];
            $ratio = $height > 0 ? $width / $height : 0;
            $checks[] = $this->check(
                5,
                $width >= 1200 && $height >= 630 && $ratio >= 1.8 && $ratio <= 2.0 ? 'pass' : ($width >= 600 ? 'warn' : 'fail'),
                "Social image is {$width}×{$height} (recommended 1200×630)",
                'Smaller or oddly shaped images get cropped or shown as a small thumbnail.',
            );
        }

        $checks[] = $this->check(5, $input->index ? 'pass' : 'warn', $input->index ? 'Page can be indexed' : 'Page is set to noindex — it will not appear in search', 'Only hide pages you deliberately keep out of Google.');

        if ($content !== null && $content !== '') {
            $words = str_word_count(strip_tags($content));
            $checks[] = $this->check(5, $words >= 300 ? 'pass' : ($words >= 100 ? 'warn' : 'fail'), "Page has {$words} words of content", 'Thin pages rarely rank; 300+ useful words is a good floor.');
        }

        $total = array_sum(array_column($checks, 'weight'));
        $earned = array_sum(array_map(fn (array $check) => match ($check['status']) {
            'pass' => $check['weight'],
            'warn' => $check['weight'] / 2,
            default => 0,
        }, $checks));

        return [
            'score' => $total > 0 ? (int) round(($earned / $total) * 100) : 0,
            'checks' => array_map(fn (array $check) => ['label' => $check['label'], 'status' => $check['status'], 'hint' => $check['hint']], $checks),
        ];
    }

    public static function colorFor(int $score): string
    {
        return match (true) {
            $score >= 80 => 'success',
            $score >= 50 => 'warning',
            default => 'danger',
        };
    }

    /**
     * @return array{weight: int, status: string, label: string, hint: string}
     */
    protected function check(int $weight, string $status, string $label, string $hint): array
    {
        return compact('weight', 'status', 'label', 'hint');
    }

    protected function contains(string $haystack, string $needle): bool
    {
        return $needle !== '' && str_contains(Str::lower(Str::ascii($haystack)), Str::lower(Str::ascii($needle)));
    }

    protected function isHomepage(string $url): bool
    {
        return in_array((string) parse_url($url, PHP_URL_PATH), ['', '/'], true);
    }
}
