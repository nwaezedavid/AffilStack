<?php

namespace App\Services\Agents\Customer;

use App\Models\CrmList;
use App\Models\LeadRun;
use App\Models\Offer;
use App\Models\User;
use App\Services\Leads\LeadFinder;
use App\Services\Leads\LeadFinderException;
use App\Support\Agents\AgentSettings;

/**
 * Lead Hunter: every night, tops each product's lead list up to the
 * customer's target with a background lead search (at most three products
 * a night). LeadFinder reports the strong leads when each search ends.
 */
class LeadHunter
{
    public const PRODUCTS_PER_NIGHT = 3;

    public function __construct(protected LeadFinder $finder) {}

    public function runFor(User $user): int
    {
        if (! AgentSettings::enabled($user, 'lead_hunter') || ! $this->finder->isAvailable()) {
            return 0;
        }

        $target = max(10, (int) AgentSettings::get($user, 'lead_hunter', 'target', 50));
        $started = 0;

        foreach ($user->offers()->where('status', 'ready')->where('is_sandbox', false)->latest()->get() as $offer) {
            if ($started >= self::PRODUCTS_PER_NIGHT) {
                break;
            }

            $gap = $target - CrmList::forOffer($offer)->contacts()->count();

            if ($gap <= 0 || $this->searchedRecently($offer)) {
                continue;
            }

            try {
                $this->finder->queue($user, $offer, $this->runSize($gap), 'hunter_agent');
                $started++;
            } catch (LeadFinderException) {
                break; // Out of allowance, credits or today's cap — try again tomorrow.
            }
        }

        return $started;
    }

    protected function searchedRecently(Offer $offer): bool
    {
        return LeadRun::query()->where('offer_id', $offer->id)->where('created_at', '>=', now()->subHours(20))->exists();
    }

    protected function runSize(int $gap): int
    {
        foreach ((array) config('leads.run_sizes') as $size) {
            if ($size >= $gap) {
                return (int) $size;
            }
        }

        return (int) max((array) config('leads.run_sizes'));
    }
}
