<?php

namespace App\Services\Agents\Customer;

use App\Models\AgentActivity;
use App\Models\Earning;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Services\Links\LinkCloakingService;
use App\Support\Agents\AgentSettings;

/**
 * Commission Watchdog: once a day, checks every product's affiliate link,
 * its network program and its sales. A broken link with a working
 * alternative (the network's tracked link or the customer's own) becomes
 * a one-tap "swap" for the customer to approve; everything else is an
 * alert. The same problem is raised at most once a week.
 */
class CommissionWatchdog
{
    public function __construct(protected LinkChecker $links) {}

    public function runFor(User $user): int
    {
        if (! AgentSettings::enabled($user, 'commission_watchdog')) {
            return 0;
        }

        $issues = 0;

        $user->offers()->where('status', 'ready')->where('is_sandbox', false)->with('networkProgram.connection')->get()
            ->each(function (Offer $offer) use ($user, &$issues): void {
                $issues += $this->checkLink($user, $offer) + $this->checkProgram($user, $offer) + $this->checkSales($user, $offer);
            });

        return $issues;
    }

    protected function checkLink(User $user, Offer $offer): int
    {
        if (blank($offer->affiliate_link) && blank($offer->network_link)) {
            return 0;
        }

        $destination = LinkCloakingService::destinationFor($offer);
        $result = $this->links->check($destination);

        if ($result['status'] !== 'broken' || $this->recentlyRaised($user, $offer, 'broken_link')) {
            return 0;
        }

        $alternative = $offer->use_network_link ? $offer->affiliate_link : $offer->network_link;
        $alternativeWorks = filled($alternative) && $alternative !== $destination && $this->links->check((string) $alternative)['status'] === 'ok';

        $summary = "The affiliate link for {$offer->product_name} isn't working".($result['code'] ? " (error {$result['code']})" : '').'.'
            .($alternativeWorks ? ' Your other link for it works — approve to switch every tracked link to it.' : ' Update it on the product page so you don\'t lose sales.');

        AgentSettings::log($user, 'commission_watchdog', 'broken_link', $summary, $offer, array_filter([
            'issue' => 'broken_link',
            'offer_id' => $offer->id,
            'url' => $destination,
            'approval' => $alternativeWorks ? 'swap_link' : null,
            'use_network_link' => $alternativeWorks ? ! $offer->use_network_link : null,
        ], fn ($value) => $value !== null), $alternativeWorks ? AgentActivity::STATUS_NEEDS_APPROVAL : AgentActivity::STATUS_DONE);

        $user->notify(new AgentUpdate('commission_watchdog', "Broken link: {$offer->product_name}", $summary, route('agents.index'), forceMail: true));

        return 1;
    }

    protected function checkProgram(User $user, Offer $offer): int
    {
        $program = $offer->networkProgram;

        if (! $program || $this->recentlyRaised($user, $offer, 'program_paused')) {
            return 0;
        }

        $lastSync = $program->connection?->programs_synced_at ?? $program->connection?->last_synced_at;
        $missing = $lastSync && $program->last_seen_at && $program->last_seen_at->lt($lastSync->copy()->subDays(2));

        if ($program->status === 'joined' && ! $missing) {
            return 0;
        }

        $summary = "{$program->name} on ".ucfirst((string) $program->connection?->network).' looks paused or no longer approved for you. Check the program on the network before sending more traffic.';
        AgentSettings::log($user, 'commission_watchdog', 'program_paused', $summary, $offer, ['issue' => 'program_paused', 'offer_id' => $offer->id]);
        $user->notify(new AgentUpdate('commission_watchdog', "Check your program: {$program->name}", $summary, route('agents.index')));

        return 1;
    }

    protected function checkSales(User $user, Offer $offer): int
    {
        $recent = Earning::query()->where('user_id', $user->id)->where('offer_id', $offer->id)->where('converted_at', '>=', now()->subDays(14))->count();
        $before = Earning::query()->where('user_id', $user->id)->where('offer_id', $offer->id)->whereBetween('converted_at', [now()->subDays(28), now()->subDays(14)])->count();

        if ($before < 4 || $recent > intdiv($before, 2) || $this->recentlyRaised($user, $offer, 'sales_drop')) {
            return 0;
        }

        $summary = "Sales for {$offer->product_name} fell from {$before} to {$recent} in the last two weeks. Check the offer is still live and try a fresh angle or channel.";
        AgentSettings::log($user, 'commission_watchdog', 'sales_drop', $summary, $offer, ['issue' => 'sales_drop', 'offer_id' => $offer->id, 'before' => $before, 'recent' => $recent]);
        $user->notify(new AgentUpdate('commission_watchdog', "Sales dropped: {$offer->product_name}", $summary, route('offers.show', $offer)));

        return 1;
    }

    protected function recentlyRaised(User $user, Offer $offer, string $issue): bool
    {
        return AgentActivity::query()
            ->where('user_id', $user->id)
            ->where('agent', 'commission_watchdog')
            ->where('action', $issue)
            ->where('subject_type', $offer->getMorphClass())
            ->where('subject_id', $offer->id)
            ->where('created_at', '>=', now()->subDays(7))
            ->exists();
    }
}
