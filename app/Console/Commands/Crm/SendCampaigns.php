<?php

namespace App\Console\Commands\Crm;

use App\Models\CrmCampaign;
use App\Services\Crm\CampaignManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * The campaign safety net, every five minutes: sends whatever is still
 * queued (e.g. held back by yesterday's daily cap) and follow-ups that have
 * come due, campaign by campaign within one time budget so a cron tick on
 * shared hosting always finishes.
 */
#[Signature('crm:send-campaigns')]
#[Description('Send queued CRM campaign emails and due follow-ups within the daily caps')]
class SendCampaigns extends Command
{
    public function handle(CampaignManager $manager): void
    {
        $deadline = microtime(true) + (int) config('crm.sending.time_budget', 40);
        $sent = 0;

        $campaigns = CrmCampaign::query()
            ->whereIn('status', ['sending', 'sent'])
            ->whereHas('recipients', fn ($q) => $q->where('status', 'queued')
                ->orWhere(fn ($q) => $q->where('status', 'sent')->whereNull('replied_at')->whereNotNull('next_step_due_at')->where('next_step_due_at', '<=', now())))
            ->orderBy('last_sent_at')
            ->limit(100)
            ->get();

        foreach ($campaigns as $campaign) {
            if (microtime(true) >= $deadline - 3) {
                break;
            }

            $sent += $manager->process($campaign, $deadline)['sent'];
        }

        $this->info("Sent {$sent} campaign email(s) across {$campaigns->count()} campaign(s).");
    }
}
