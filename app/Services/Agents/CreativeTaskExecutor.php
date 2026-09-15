<?php

namespace App\Services\Agents;

use App\Models\AgentTask;
use App\Models\FaqItem;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * Actually publishes a creative AgentTask's proposed fields once a
 * super-admin approves it — see CreativeTaskApprovalService, which calls
 * this synchronously right after approving, and ExecuteDueAgentTasks,
 * which calls it again as a safety net for any task that's still
 * 'scheduled' by the time its cron pass runs (it never is, in the normal
 * path, since approval already moved it past 'scheduled' by then).
 */
class CreativeTaskExecutor
{
    public function execute(AgentTask $task): void
    {
        $task->update(['status' => AgentTask::STATUS_RUNNING]);

        try {
            $result = match ($task->type) {
                TonyAgentService::KIND_SITE_PAGE => $this->publishSitePage($task),
                TonyAgentService::KIND_HOMEPAGE_FEATURE => $this->publishHomepageFeature($task),
                TonyAgentService::KIND_FAQ_ITEM => $this->publishFaqItem($task),
                TonyAgentService::KIND_BRANDING => $this->publishBranding($task),
                default => throw new RuntimeException("Unknown creative task type '{$task->type}'."),
            };

            $task->update([
                'status' => AgentTask::STATUS_COMPLETED,
                'executed_at' => now(),
                'result' => $result,
            ]);
        } catch (Throwable $e) {
            Log::error('Tony: publishing a creative task failed.', ['task_id' => $task->id, 'error' => $e->getMessage()]);

            $task->update([
                'status' => AgentTask::STATUS_FAILED,
                'executed_at' => now(),
                'result' => $e->getMessage(),
            ]);
        }
    }

    protected function publishSitePage(AgentTask $task): string
    {
        $fields = $this->fields($task);
        $fields['is_published'] = true;

        $page = $this->targetId($task) ? SitePage::findOrFail($this->targetId($task)) : new SitePage;
        $page->fill($fields);
        $page->save();

        return "Published page \"{$page->title}\" at /{$page->slug}.";
    }

    protected function publishHomepageFeature(AgentTask $task): string
    {
        $fields = $this->fields($task);
        $fields['is_active'] = true;

        $feature = $this->targetId($task) ? HomepageFeature::findOrFail($this->targetId($task)) : new HomepageFeature;
        $feature->fill($fields);
        $feature->save();

        return "Published homepage feature \"{$feature->title}\".";
    }

    protected function publishFaqItem(AgentTask $task): string
    {
        $fields = $this->fields($task);
        $fields['is_published'] = true;

        $item = $this->targetId($task) ? FaqItem::findOrFail($this->targetId($task)) : new FaqItem;
        $item->fill($fields);
        $item->save();

        return "Published FAQ entry \"{$item->question}\".";
    }

    protected function publishBranding(AgentTask $task): string
    {
        $fields = $this->fields($task);

        foreach ($fields as $key => $value) {
            SiteSetting::set($key, $value);
        }

        return $fields === []
            ? 'No branding fields were proposed — nothing to publish.'
            : 'Updated branding: '.implode(', ', array_keys($fields)).'.';
    }

    /**
     * @return array<string, mixed>
     */
    protected function fields(AgentTask $task): array
    {
        return (array) ($task->payload['fields'] ?? []);
    }

    protected function targetId(AgentTask $task): ?int
    {
        return $task->payload['target_id'] ?? null;
    }
}
