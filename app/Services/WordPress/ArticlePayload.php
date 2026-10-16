<?php

namespace App\Services\WordPress;

use App\Models\Generation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Turns a finished AffilStack blog article into what the AffilStack
 * Connector plugin creates a WordPress draft from: HTML content with the
 * customer's tracked affiliate link and disclosure already in place, SEO
 * fields, categories and tags, FAQ entries (for FAQ schema) and a
 * featured image.
 */
class ArticlePayload
{
    public function __construct(protected CoverImageRenderer $covers) {}

    /**
     * @return array{external_id: string, title: string, slug: string, content: string, excerpt: string, seo: array{title: string, description: string, focus_keyword: string}, categories: array<int, string>, tags: array<int, string>, faq: array<int, array{question: string, answer: string}>, featured_image: array{url: string, alt: string}|null, source: array{product: string, generation_id: int}}
     */
    public function build(Generation $generation): array
    {
        $meta = (array) ($generation->output_meta ?? []);
        $offer = $generation->offer;
        $title = trim((string) ($meta['title'] ?? 'New article'));
        // The title comes off before the disclosure goes on top.
        $body = $this->stripLeadingTitle((string) $generation->output, $title);
        $markdown = $offer ? $offer->cloak($body, $generation->module, generation: $generation) : $body;
        $faq = static::faq($meta['faq'] ?? []);

        $content = Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        if ($faq !== [] && ! preg_match('/<h[23][^>]*>\s*(frequently asked|faqs?\b)/i', $content)) {
            $content .= "\n<h2>Frequently asked questions</h2>\n";
            foreach ($faq as $item) {
                $content .= '<h3>'.e($item['question'])."</h3>\n<p>".e($item['answer'])."</p>\n";
            }
        }

        $coverPath = $this->covers->coverFor($generation);

        return [
            'external_id' => 'affilstack-generation-'.$generation->id,
            'title' => $title,
            'slug' => Str::slug((string) ($meta['slug'] ?? $title)) ?: Str::slug($title),
            'content' => trim($content),
            'excerpt' => trim((string) ($meta['meta_description'] ?? '')),
            'seo' => [
                'title' => Str::limit($title, 120, '', preserveWords: true),
                'description' => Str::limit(trim((string) ($meta['meta_description'] ?? '')), 300, '', preserveWords: true),
                'focus_keyword' => trim((string) ($meta['target_keyword'] ?? $generation->input['target_keyword'] ?? '')),
            ],
            'categories' => array_values(array_filter([Str::limit(trim((string) ($meta['suggested_category'] ?? '')), 60, '')])),
            'tags' => collect((array) ($meta['suggested_tags'] ?? []))
                ->map(fn ($tag) => Str::limit(trim((string) $tag), 40, ''))
                ->filter()
                ->unique()
                ->take(8)
                ->values()
                ->all(),
            'faq' => $faq,
            'featured_image' => $coverPath ? [
                'url' => Storage::disk('public')->url($coverPath),
                'alt' => $title,
            ] : null,
            'source' => [
                'product' => (string) ($offer?->product_name ?? ''),
                'generation_id' => $generation->id,
            ],
        ];
    }

    /**
     * @return array<int, array{question: string, answer: string}>
     */
    public static function faq(mixed $items): array
    {
        return collect(is_array($items) ? $items : [])
            ->map(fn ($item) => is_array($item) ? [
                'question' => Str::limit(trim(strip_tags((string) ($item['question'] ?? $item['q'] ?? ''))), 200, ''),
                'answer' => Str::limit(trim(strip_tags((string) ($item['answer'] ?? $item['a'] ?? ''))), 1200, ''),
            ] : null)
            ->filter(fn ($item) => $item && $item['question'] !== '' && $item['answer'] !== '')
            ->take(8)
            ->values()
            ->all();
    }

    /**
     * WordPress shows the post title itself, so an H1 repeating it at the
     * top of the article body is dropped.
     */
    protected function stripLeadingTitle(string $markdown, string $title): string
    {
        return (string) preg_replace('/\A\s*#\s+'.preg_quote($title, '/').'\s*\n/u', '', $markdown);
    }
}
