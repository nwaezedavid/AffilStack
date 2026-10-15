<?php

namespace App\Services\Blog;

use App\Services\AI\AIProvider;
use App\Support\Brand;
use App\Support\SafeHtml;
use Illuminate\Support\Str;

/**
 * "Draft with AI" for Admin → Website → Blog posts: a full, SEO-ready
 * article about AffilStack's own topics (affiliate marketing, the tools),
 * returned as form values to review and edit before sending to WordPress.
 * Runs on the platform's AI engine — no customer credits involved.
 */
class BlogPostWriter
{
    public function __construct(protected AIProvider $ai) {}

    /**
     * @return array{title: string, content: string, excerpt: string, focus_keyword: string, seo_title: string, seo_description: string, categories: array<int, string>, tags: array<int, string>}
     */
    public function draft(string $topic, ?string $keyword = null, ?string $notes = null): array
    {
        $site = Brand::name();

        $system = <<<PROMPT
        You write the official blog of {$site}, an all-in-one AI platform for affiliate marketers (product research, finding buyers on Google Maps and LinkedIn, CRM email campaigns, content for every channel, tracked links and earnings). Readers are affiliate marketers and small online entrepreneurs in Africa, the diaspora and worldwide.

        Write genuinely useful, specific, practical articles: concrete steps, examples and numbers where honest, short paragraphs, descriptive H2/H3 subheadings. Plain, warm, confident English. No hype, no invented statistics, no fake quotes. Mention {$site} only where it naturally helps the reader (at most twice), never as a hard sell.

        Reply with JSON only:
        {"title": "...", "content_html": "<p>...</p><h2>...</h2>...", "excerpt": "one or two sentences", "focus_keyword": "...", "seo_title": "max 60 characters", "seo_description": "max 155 characters", "categories": ["one or two"], "tags": ["three to six"]}
        content_html uses only <p>, <h2>, <h3>, <ul>, <ol>, <li>, <strong>, <em>, <blockquote> and <a href="https://...">. 900–1,500 words. No <h1>.
        PROMPT;

        $user = 'Topic: '.trim($topic)
            .(filled($keyword) ? "\nFocus keyword: ".trim((string) $keyword) : '')
            .(filled($notes) ? "\nNotes from the editor: ".trim((string) $notes) : '');

        $result = $this->ai->generateJson($system, $user);

        $list = fn (mixed $value, int $max): array => collect(is_array($value) ? $value : [])
            ->map(fn ($item): string => Str::limit(trim(strip_tags((string) $item)), 60, ''))
            ->filter()->unique()->take($max)->values()->all();

        return [
            'title' => Str::limit(trim(strip_tags((string) ($result['title'] ?? $topic))), 200, ''),
            'content' => SafeHtml::clean((string) ($result['content_html'] ?? '')),
            'excerpt' => Str::limit(trim(strip_tags((string) ($result['excerpt'] ?? ''))), 400, ''),
            'focus_keyword' => Str::limit(trim(strip_tags((string) ($result['focus_keyword'] ?? $keyword ?? ''))), 120, ''),
            'seo_title' => Str::limit(trim(strip_tags((string) ($result['seo_title'] ?? ''))), 70, ''),
            'seo_description' => Str::limit(trim(strip_tags((string) ($result['seo_description'] ?? ''))), 170, ''),
            'categories' => $list($result['categories'] ?? [], 2),
            'tags' => $list($result['tags'] ?? [], 6),
        ];
    }
}
