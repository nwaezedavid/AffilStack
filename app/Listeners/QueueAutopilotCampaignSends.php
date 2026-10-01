<?php

namespace App\Listeners;

use App\Events\CrmContactAddedToList;
use App\Services\Crm\CampaignManager;

/**
 * Campaign autopilot: every contact newly added to a list (from any
 * source) is queued for that list's launched autopilot campaigns. Runs
 * inline — it only inserts recipient rows and dispatches a queued
 * SendCampaignBatch, so adding a contact stays fast.
 */
class QueueAutopilotCampaignSends
{
    public function __construct(protected CampaignManager $manager) {}

    public function handle(CrmContactAddedToList $event): void
    {
        $campaigns = $event->list->campaigns()
            ->where('autopilot', true)
            ->whereIn('status', ['approved', 'sending', 'sent', 'paused'])
            ->get();

        foreach ($campaigns as $campaign) {
            $this->manager->queueForAutopilot($campaign, $event->contact);
        }
    }
}
