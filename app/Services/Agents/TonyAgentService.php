<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\AI\AIProvider;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Tony, the Creative Agent (AI agents phase, agent #4 of 4): drafts site
 * content and branding/copy changes from a plain-language brief, but never
 * touches the live site itself — every draft(...) call only ever creates a
 * pending AgentTask (agent=creative), the same shared permission-request
 * queue Tom's fixes use. A super-admin previews it (rendered through the
 * real public templates — see CreativeTaskPreviewController) and either
 * approves (CreativeTaskApprovalService, which runs CreativeTaskExecutor
 * immediately) or declines it; nothing Tony proposes ever reaches a real
 * visitor without that one human click.
 *
 * Every proposed field is filtered against an explicit per-kind allowlist
 * before it's stored — the AI's JSON output is never trusted to only
 * contain the keys it was asked for.
 */
class TonyAgentService
{
    public const KIND_SITE_PAGE = 'site_page';

    public const KIND_HOMEPAGE_FEATURE = 'homepage_feature';

    public const KIND_FAQ_ITEM = 'faq_item';

    public const KIND_BRANDING = 'branding';

    public const KINDS = [self::KIND_SITE_PAGE, self::KIND_HOMEPAGE_FEATURE, self::KIND_FAQ_ITEM, self::KIND_BRANDING];

    /**
     * The only SiteSetting keys Tony may ever propose changing — deliberately
     * excludes the color pickers (not yet wired into any public template,
     * so "previewing" a color change would misleadingly show no difference)
     * and file uploads (logo/favicon/hero image), which need an actual
     * upload rather than AI-generated text.
     */
    public const BRANDING_KEYS = [
        'site_name', 'header_announcement', 'footer_text',
        'hero_headline', 'hero_subheadline', 'menu_items',
    ];

    public function __construct(protected AIProvider $ai) {}

    public function draft(string $kind, string $brief, User $admin, ?int $targetId = null): AgentTask
    {
        if (! in_array($kind, self::KINDS, true)) {
            throw new InvalidArgumentException("Unknown creative draft kind '{$kind}'.");
        }

        [$fields, $title] = match ($kind) {
            self::KIND_SITE_PAGE => $this->draftSitePage($brief, $targetId),
            self::KIND_HOMEPAGE_FEATURE => $this->draftHomepageFeature($brief, $targetId),
            self::KIND_FAQ_ITEM => $this->draftFaqItem($brief, $targetId),
            self::KIND_BRANDING => $this->draftBranding($brief),
        };

        return AgentTask::create([
            'agent' => AgentTask::AGENT_CREATIVE,
            'type' => $kind,
            'title' => $title,
            'summary' => $brief,
            'payload' => ['target_id' => $targetId, 'fields' => $fields],
            'risk_level' => 'low',
            'status' => AgentTask::STATUS_PENDING,
            'requested_by_id' => $admin->id,
        ]);
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function draftSitePage(string $brief, ?int $targetId): array
    {
        $existing = $targetId ? SitePage::findOrFail($targetId) : null;

        $system = 'You are Tony, the creative agent for AffilStack, a SaaS for affiliate marketers. '
            .'You draft static page copy for a super-admin to review before it goes live. Write clear, '
            .'on-brand copy in semantic HTML using only <h2>, <h3>, <p>, <ul>, <ol>, <li>, <strong>, and '
            .'<a> tags — no <script>, <style>, <iframe>, or inline event handlers. Respond with ONLY '
            .($existing
                ? '{"title": string, "meta_description": string, "content": string}.'
                : '{"title": string, "slug": string (lowercase, hyphenated), "meta_description": string, "content": string}.');

        $user = $existing
            ? "Revise this page.\nCurrent title: {$existing->title}\nCurrent content:\n{$existing->content}\n\nRequested change: {$brief}"
            : "Draft a brand-new page.\n\n{$brief}";

        $fields = array_intersect_key(
            $this->ai->generateJson($system, $user),
            array_flip(['title', 'slug', 'meta_description', 'content']),
        );

        if ($existing) {
            unset($fields['slug']); // never let Tony change an existing page's URL
        } else {
            $fields['slug'] = $this->uniqueSlug((string) ($fields['slug'] ?? $fields['title'] ?? 'new-page'));
        }

        return [$fields, 'Page: '.($fields['title'] ?? $existing?->title ?? 'Untitled')];
    }

    protected function uniqueSlug(string $seed): string
    {
        $base = Str::slug($seed) ?: 'page';
        $slug = $base;
        $suffix = 2;

        while (SitePage::where('slug', $slug)->exists()) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function draftHomepageFeature(string $brief, ?int $targetId): array
    {
        $existing = $targetId ? HomepageFeature::findOrFail($targetId) : null;

        $system = 'You are Tony, the creative agent for AffilStack. Draft a short homepage feature card. '
            .'Respond with ONLY {"title": string, "description": string (1-2 sentences), "icon": string (a single emoji)}.';

        $user = $existing
            ? "Revise this feature card.\nCurrent title: {$existing->title}\nCurrent description: {$existing->description}\n\nRequested change: {$brief}"
            : "Draft a new feature card.\n\n{$brief}";

        $fields = array_intersect_key(
            $this->ai->generateJson($system, $user),
            array_flip(['title', 'description', 'icon']),
        );

        if (! $existing) {
            $fields['sort_order'] = (int) (HomepageFeature::max('sort_order') ?? 0) + 1;
        }

        return [$fields, 'Homepage feature: '.($fields['title'] ?? $existing?->title ?? 'Untitled')];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function draftFaqItem(string $brief, ?int $targetId): array
    {
        $existing = $targetId ? FaqItem::findOrFail($targetId) : null;
        $categories = FaqItem::query()->distinct()->pluck('category')->filter()->implode(', ') ?: 'general';

        $system = 'You are Tony, the creative agent for AffilStack. Draft an FAQ entry. Existing categories '
            ."(reuse one if it fits, otherwise propose a short new lowercase one-word category): {$categories}. "
            .'Respond with ONLY {"question": string, "answer": string, "category": string}.';

        $user = $existing
            ? "Revise this FAQ entry.\nCurrent question: {$existing->question}\nCurrent answer: {$existing->answer}\n\nRequested change: {$brief}"
            : "Draft a new FAQ entry.\n\n{$brief}";

        $fields = array_intersect_key(
            $this->ai->generateJson($system, $user),
            array_flip(['question', 'answer', 'category']),
        );

        if (! $existing) {
            $fields['sort_order'] = (int) (FaqItem::max('sort_order') ?? 0) + 1;
        }

        return [$fields, 'FAQ: '.($fields['question'] ?? $existing?->question ?? 'Untitled')];
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    protected function draftBranding(string $brief): array
    {
        $current = collect(self::BRANDING_KEYS)->mapWithKeys(fn ($key) => [$key => SiteSetting::get($key)]);

        $system = 'You are Tony, the creative agent for AffilStack. Propose changes to the site\'s branding/copy. '
            .'Only include the specific keys that should change out of: '.implode(', ', self::BRANDING_KEYS)
            .' (menu_items is an array of {"label": string, "url": string}). Leave out any key that should stay '
            .'the same. Respond with ONLY a JSON object of those keys.';

        $user = "Current values:\n".json_encode($current, JSON_PRETTY_PRINT)."\n\nRequested change: {$brief}";

        $fields = array_intersect_key($this->ai->generateJson($system, $user), array_flip(self::BRANDING_KEYS));

        return [$fields, 'Branding: '.(implode(', ', array_keys($fields)) ?: 'no changes proposed')];
    }
}
