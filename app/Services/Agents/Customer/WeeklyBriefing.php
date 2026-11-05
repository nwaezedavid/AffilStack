<?php

namespace App\Services\Agents\Customer;

use App\Models\AgentActivity;
use App\Models\CrmReply;
use App\Models\Generation;
use App\Models\User;
use App\Notifications\WeeklyBriefingNotification;
use App\Services\Credits\CreditManager;
use App\Services\Dashboard\ProgressCounter;
use App\Services\Leads\LeadAllowance;
use App\Support\Agents\AgentSettings;

/**
 * Weekly briefing: a Monday email covering what the agents did in the last
 * seven days, the week's results from the progress counter and the three
 * best next steps (worked out from the account itself — no AI call, so it
 * never costs the customer credits).
 */
class WeeklyBriefing
{
    public function __construct(protected ProgressCounter $progress, protected CreditManager $credits, protected LeadAllowance $leads) {}

    public function runFor(User $user): bool
    {
        if (! AgentSettings::enabled($user, 'weekly_briefing')) {
            return false;
        }

        $alreadySent = AgentActivity::query()->where('user_id', $user->id)->where('agent', 'weekly_briefing')->where('created_at', '>=', now()->subDays(6))->exists();

        if ($alreadySent) {
            return false;
        }

        ProgressCounter::forget($user);
        $briefing = $this->build($user);
        $user->notify(new WeeklyBriefingNotification($briefing));
        AgentSettings::log($user, 'weekly_briefing', 'sent', 'Sent your weekly briefing.');

        return true;
    }

    /**
     * @return array{wins: array<int, string>, agents: array<int, string>, next_steps: array<int, array{text: string, url: string}>, needs_approval: int}
     */
    public function build(User $user): array
    {
        $progress = $this->progress->for($user);

        $wins = collect($progress['tiles'])
            ->filter(fn (array $tile): bool => $tile['week'] > 0)
            ->map(fn (array $tile): string => '+'.number_format($tile['week']).' '.mb_strtolower($tile['label']))
            ->values()
            ->all();

        $agents = AgentActivity::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subWeek())
            ->where('agent', '!=', 'weekly_briefing')
            ->selectRaw('agent, count(*) as total')->groupBy('agent')->pluck('total', 'agent')
            ->map(fn ($total, string $agent): string => (AgentSettings::AGENTS[$agent]['label'] ?? $agent).': '.$total.' '.str('action')->plural((int) $total))
            ->values()
            ->all();

        return [
            'wins' => $wins,
            'agents' => $agents,
            'next_steps' => array_slice($this->nextSteps($user), 0, 3),
            'needs_approval' => AgentActivity::query()->where('user_id', $user->id)->where('status', AgentActivity::STATUS_NEEDS_APPROVAL)->count(),
        ];
    }

    /**
     * @return array<int, array{text: string, url: string}>
     */
    protected function nextSteps(User $user): array
    {
        $steps = [];

        $waiting = CrmReply::query()->where('user_id', $user->id)->whereIn('copilot_status', ['drafted'])->count();
        if ($waiting > 0) {
            $steps[] = ['text' => "Send the {$waiting} ".str('reply')->plural($waiting).' Reply Copilot drafted for you.', 'url' => route('crm.inbox')];
        }

        $approvals = AgentActivity::query()->where('user_id', $user->id)->where('status', AgentActivity::STATUS_NEEDS_APPROVAL)->count();
        if ($approvals > 0) {
            $steps[] = ['text' => "Approve or dismiss {$approvals} agent ".str('suggestion')->plural($approvals).'.', 'url' => route('agents.index')];
        }

        $drafts = Generation::query()->where('user_id', $user->id)->where('status', 'completed')->where('calendar_status', 'draft')->where('scheduled_for', '<=', now()->addDays(7))->count();
        if ($drafts > 0) {
            $steps[] = ['text' => "Publish the {$drafts} ".str('draft')->plural($drafts).' planned in your content calendar.', 'url' => route('calendar.index')];
        }

        if (! $user->emailConnection?->isVerified()) {
            $steps[] = ['text' => 'Connect your email so campaigns and Reply Copilot can work for you.', 'url' => route('email-connections.index')];
        }

        $free = $this->leads->freeRemaining($user);
        if ($free > 0 && $free !== PHP_INT_MAX && ! AgentSettings::enabled($user, 'lead_hunter')) {
            $steps[] = ['text' => "You have {$free} free leads left this month — switch on Lead Hunter to use them.", 'url' => route('agents.index')];
        }

        if ($user->offers()->where('status', 'ready')->doesntExist()) {
            $steps[] = ['text' => 'Research your first product so your agents know what to sell.', 'url' => route('offers.create')];
        }

        // Always-useful fallbacks, so the email always carries three steps.
        $steps[] = ['text' => 'Research one more product to promote — more products, more sales.', 'url' => route('offers.create')];
        $steps[] = ['text' => 'Write a fresh piece of content for your best product.', 'url' => route('offers.index')];
        $steps[] = ['text' => 'Email the leads in your CRM who haven\'t heard from you yet.', 'url' => route('crm.index')];

        return $steps;
    }
}
