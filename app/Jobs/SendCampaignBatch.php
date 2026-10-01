<?php

namespace App\Jobs;

use App\Models\CrmCampaign;
use App\Services\Crm\CampaignManager;
use App\Services\Crm\SendingQuota;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Sends the next paced batch of one campaign (see CampaignManager::process)
 * and, while more is due and today's cap allows, queues itself again a
 * minute later (not on the sync driver, where that would recurse). Unique until processing so a burst of autopilot additions
 * collapses into one pending run; the scheduled crm:send-campaigns command
 * picks up anything left (tomorrow's cap, due follow-ups).
 */
class SendCampaignBatch implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct(public int $campaignId) {}

    public function uniqueId(): string
    {
        return (string) $this->campaignId;
    }

    public function handle(CampaignManager $manager, SendingQuota $quota): void
    {
        $campaign = CrmCampaign::find($this->campaignId);

        if (! $campaign) {
            return;
        }

        $result = $manager->process($campaign);

        if ($result['more'] && $result['reason'] !== 'daily_cap' && $quota->remaining($campaign->user) > 0 && config('queue.default') !== 'sync') {
            self::dispatch($campaign->id)->delay(now()->addMinute());
        }
    }
}
