<?php

namespace App\Services\Agents\Customer;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\TrackedLink;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\BlogArticleService;
use App\Services\Modules\LinkedInService;
use App\Services\Modules\XService;
use App\Support\Agents\AgentSettings;
use Throwable;

/**
 * Content Autopilot: each Monday, writes the customer's chosen number of
 * pieces for the week across their products and queues them in the content
 * calendar (as drafts on planned days — nothing is published without them).
 * Formats that got the most clicks in the last 30 days go first.
 */
class ContentAutopilot
{
    /**
     * Module => plan channel that must include it.
     *
     * @var array<string, string>
     */
    public const FORMATS = [
        'linkedin_post' => 'linkedin',
        'x_thread' => 'x',
        'blog_article' => 'blog',
    ];

    public function runFor(User $user): int
    {
        if (! AgentSettings::enabled($user, 'content_autopilot')) {
            return 0;
        }

        $offers = $user->offers()->where('status', 'ready')->where('is_sandbox', false)->latest()->take(5)->get();
        $formats = $this->formatsFor($user);

        if ($offers->isEmpty() || $formats === []) {
            return 0;
        }

        $perWeek = max(1, min(7, (int) AgentSettings::get($user, 'content_autopilot', 'per_week', 3)));
        $timezone = $user->timezone ?: config('app.timezone');
        $queued = 0;

        for ($i = 0; $i < $perWeek; $i++) {
            $offer = $offers[$i % $offers->count()];
            $module = $formats[$i % count($formats)];

            try {
                $generation = $this->queue($user, $offer, $module);
            } catch (InsufficientCreditsException) {
                break;
            } catch (Throwable $e) {
                report($e);

                continue;
            }

            // Spread across the week at 10:00 in the customer's own timezone.
            $day = now($timezone)->startOfWeek()->addDays((int) floor($i * 7 / $perWeek))->setTime(10, 0);
            $generation->update(['calendar_status' => 'draft', 'scheduled_for' => $day->max(now($timezone)->addHour())->utc()]);
            $queued++;
        }

        if ($queued > 0) {
            AgentSettings::log($user, 'content_autopilot', 'planned_week', "Wrote {$queued} ".str('piece')->plural($queued).' of content for this week and added them to your calendar as drafts.');
            $user->notify(new AgentUpdate('content_autopilot', "This week's content is ready to review", "{$queued} ".str('piece')->plural($queued).' are in your content calendar. Review and publish them on their planned days.', route('calendar.index')));
        }

        return $queued;
    }

    /**
     * @return array<int, string>
     */
    public function formatsFor(User $user): array
    {
        $allowed = collect(self::FORMATS)->filter(fn (string $channel): bool => $user->canUseChannel($channel))->keys();

        $clicks = TrackedLink::query()->where('user_id', $user->id)
            ->where('updated_at', '>=', now()->subDays(30))
            ->selectRaw('module, sum(clicks_count) as clicks')->groupBy('module')->pluck('clicks', 'module');

        return $allowed->sortByDesc(fn (string $module): int => (int) ($clicks[$module] ?? 0))->values()->all();
    }

    protected function queue(User $user, Offer $offer, string $module): Generation
    {
        return match ($module) {
            'linkedin_post' => app(LinkedInService::class)->queue($user, $offer, 'post'),
            'x_thread' => app(XService::class)->queue($user, $offer),
            'blog_article' => app(BlogArticleService::class)->queue($user, $offer),
        };
    }
}
