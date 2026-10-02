<?php

namespace App\Support\Seo;

/**
 * The admin-entered SEO fields for one public page, normalised into a single
 * shape whether they came from a PageSeo row (route-based pages), a SitePage
 * row, or a Filament form's live state — so SeoHeadBuilder, SeoScore and the
 * search-appearance preview never need to know which one they are reading.
 * Every string is trimmed and blank strings become null.
 */
final class SeoInput
{
    /**
     * @param  array<int, string>  $secondaryKeywords
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly ?string $seoTitle = null,
        public readonly ?string $metaDescription = null,
        public readonly ?string $focusKeyword = null,
        public readonly array $secondaryKeywords = [],
        public readonly array $tags = [],
        public readonly ?string $canonicalUrl = null,
        public readonly bool $index = true,
        public readonly bool $follow = true,
        public readonly ?string $ogTitle = null,
        public readonly ?string $ogDescription = null,
        public readonly ?string $ogImagePath = null,
        public readonly ?string $twitterTitle = null,
        public readonly ?string $twitterDescription = null,
        public readonly ?string $twitterImagePath = null,
        public readonly ?string $schemaType = null,
        public readonly ?string $customSchema = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data  snake_case keys as stored on the models / used in the forms
     */
    public static function fromArray(array $data): self
    {
        return new self(
            seoTitle: self::text($data['seo_title'] ?? null),
            metaDescription: self::text($data['meta_description'] ?? null),
            focusKeyword: self::text($data['focus_keyword'] ?? null),
            secondaryKeywords: self::list($data['secondary_keywords'] ?? []),
            tags: self::list($data['tags'] ?? []),
            canonicalUrl: self::text($data['canonical_url'] ?? null),
            index: (bool) ($data['index'] ?? true),
            follow: (bool) ($data['follow'] ?? true),
            ogTitle: self::text($data['og_title'] ?? null),
            ogDescription: self::text($data['og_description'] ?? null),
            ogImagePath: self::path($data['og_image_path'] ?? null),
            twitterTitle: self::text($data['twitter_title'] ?? null),
            twitterDescription: self::text($data['twitter_description'] ?? null),
            twitterImagePath: self::path($data['twitter_image_path'] ?? null),
            schemaType: self::text($data['schema_type'] ?? null),
            customSchema: self::text($data['custom_schema'] ?? null),
        );
    }

    /**
     * Robots directive for the meta tag, or null for the default
     * "index, follow" (which needs no tag at all).
     */
    public function robotsDirective(): ?string
    {
        if ($this->index && $this->follow) {
            return null;
        }

        return ($this->index ? 'index' : 'noindex').', '.($this->follow ? 'follow' : 'nofollow');
    }

    /**
     * Focus keyword, secondary keywords and tags — de-duplicated
     * case-insensitively, focus keyword first.
     *
     * @return array<int, string>
     */
    public function allKeywords(): array
    {
        $seen = [];

        foreach ([$this->focusKeyword, ...$this->secondaryKeywords, ...$this->tags] as $keyword) {
            if ($keyword !== null && $keyword !== '' && ! isset($seen[mb_strtolower($keyword)])) {
                $seen[mb_strtolower($keyword)] = $keyword;
            }
        }

        return array_values($seen);
    }

    protected static function text(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * A Filament FileUpload's live state is an array of one path keyed by a
     * UUID (or a TemporaryUploadedFile mid-upload) — only a settled string
     * path is usable for a preview.
     */
    protected static function path(mixed $value): ?string
    {
        if (is_array($value)) {
            $value = collect($value)->first(fn ($item) => is_string($item));
        }

        return is_string($value) ? self::text($value) : null;
    }

    /**
     * @return array<int, string>
     */
    protected static function list(mixed $value): array
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?: array_map('trim', explode(',', $value));
        }

        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map(fn ($item) => is_scalar($item) ? trim((string) $item) : '', $value),
            fn (string $item) => $item !== '',
        )));
    }
}
