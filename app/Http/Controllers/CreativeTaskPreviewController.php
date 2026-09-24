<?php

namespace App\Http\Controllers;

use App\Models\AgentTask;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Services\Agents\TonyAgentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Renders exactly what a pending (or already-decided) Tony (the Creative
 * Agent) task would look like live — using the SAME public templates real
 * visitors see, with the task's proposed fields swapped in instead of
 * persisted ones — so a super-admin can approve with confidence without
 * anything ever touching the live site first. Nothing here writes to the
 * database; publishing happens separately in CreativeTaskApprovalService.
 */
class CreativeTaskPreviewController extends Controller
{
    public function show(Request $request, AgentTask $agentTask): Response
    {
        abort_unless($request->user()?->hasRole('admin'), 403);
        abort_unless($agentTask->agent === AgentTask::AGENT_CREATIVE, 404);

        $html = match ($agentTask->type) {
            TonyAgentService::KIND_SITE_PAGE => $this->renderSitePage($agentTask),
            TonyAgentService::KIND_HOMEPAGE_FEATURE => $this->renderHomepageFeature($agentTask),
            TonyAgentService::KIND_FAQ_ITEM => $this->renderFaqItem($agentTask),
            TonyAgentService::KIND_BRANDING => $this->renderBranding($agentTask),
            default => abort(404),
        };

        return response($this->withPreviewBanner($html));
    }

    protected function renderSitePage(AgentTask $task): string
    {
        $targetId = $task->payload['target_id'] ?? null;
        $page = $targetId ? SitePage::findOrFail($targetId)->replicate() : new SitePage;
        $page->fill((array) ($task->payload['fields'] ?? []));
        $page->updated_at = $page->updated_at ?: now();

        return view('marketing.page', compact('page'))->render();
    }

    protected function renderHomepageFeature(AgentTask $task): string
    {
        $targetId = $task->payload['target_id'] ?? null;
        $feature = $targetId ? HomepageFeature::findOrFail($targetId)->replicate() : new HomepageFeature;
        $feature->fill((array) ($task->payload['fields'] ?? []));
        $feature->is_active = true;

        return HomepageFeature::withPreviewFeature($feature, $targetId, fn () => view('marketing.home')->render());
    }

    protected function renderFaqItem(AgentTask $task): string
    {
        $targetId = $task->payload['target_id'] ?? null;
        $item = $targetId ? FaqItem::findOrFail($targetId)->replicate() : new FaqItem;
        $item->fill((array) ($task->payload['fields'] ?? []));
        $item->is_published = true;

        $groups = FaqItem::where('is_published', true)
            ->where('id', '!=', $targetId ?? 0)
            ->orderBy('sort_order')
            ->get()
            ->push($item)
            ->sortBy('sort_order')
            ->groupBy('category');

        $supportEmail = SiteSetting::get('support_email', 'support@affilstack.com');

        return view('marketing.help', compact('groups', 'supportEmail'))->render();
    }

    protected function renderBranding(AgentTask $task): string
    {
        return SiteSetting::withPreviewOverrides(
            (array) ($task->payload['fields'] ?? []),
            fn () => view('marketing.home')->render(),
        );
    }

    protected function withPreviewBanner(string $html): string
    {
        $banner = view('creative-tasks.preview-banner')->render();
        $withBanner = preg_replace('/<body([^>]*)>/', '<body$1>'.$banner, $html, 1);

        return $withBanner ?? $html;
    }
}
