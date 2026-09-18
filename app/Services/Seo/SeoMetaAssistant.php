<?php

namespace App\Services\Seo;

use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;

/**
 * "AI describe this page" — RankMath's own AI SEO assistant does this same
 * job. Drafts an SEO title and meta description from a page's own title and
 * plain-text content, optionally centered on a focus keyword — the admin
 * still reviews and edits before saving, same as every other AI-drafted
 * field in this app.
 */
class SeoMetaAssistant
{
    public function __construct(protected AIProvider $ai) {}

    /**
     * @return array{seo_title: string, meta_description: string}
     */
    public function suggest(string $pageTitle, string $plainTextContent, ?string $focusKeyword = null): array
    {
        $system = <<<'PROMPT'
            You write search-engine snippets for a web page: an SEO title
            (under 60 characters) and a meta description (under 155
            characters). Base them on the page's actual title and content —
            never invent claims the content doesn't support. If a focus
            keyword is given, work it naturally into both, as close to the
            front of the title as reads naturally. Write for a human reader
            first (compelling, specific, no keyword stuffing) — search
            engines reward that more than keyword density. Return ONLY JSON:
            {"seo_title": "...", "meta_description": "..."}
            PROMPT;

        $userPrompt = "Page title: {$pageTitle}\n".
            ($focusKeyword ? "Focus keyword: {$focusKeyword}\n" : '').
            'Content (truncated): '.mb_substr(trim($plainTextContent), 0, 3000);

        try {
            $result = $this->ai->generateJson($system, $userPrompt, ['temperature' => 0.4]);
        } catch (AIGenerationException $e) {
            throw new AIGenerationException('Could not reach the AI assistant right now — please try again.', previous: $e);
        }

        return [
            'seo_title' => (string) ($result['seo_title'] ?? $pageTitle),
            'meta_description' => (string) ($result['meta_description'] ?? ''),
        ];
    }
}
