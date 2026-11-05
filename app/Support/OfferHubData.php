<?php

namespace App\Support;

use App\Models\CrmList;
use App\Models\LeadRun;
use App\Models\Offer;
use App\Models\User;
use App\Services\Leads\LeadAllowance;
use App\Services\Leads\LeadFinder;
use Illuminate\Support\Collection;

/**
 * The view data behind a product's research results block — shared by the
 * Get Started dashboard, the product hub and the research status poll, so
 * all three render identical markup from the same partial.
 */
class OfferHubData
{
    /**
     * @return array{offer: Offer, research: ResearchPresenter, tiles: Collection<string, array<string, mixed>>, prospectList: ?CrmList, prospectCount: int, leads: ?array<string, mixed>}
     */
    public static function for(User $viewer, Offer $offer): array
    {
        $offer->loadMissing('generations');

        // CRM (and so a product's prospect list) is owner-only — a team
        // seat can't open any CRM route (config('agency.seat_allowed_routes')),
        // so it never gets the panel, and never creates a list by viewing.
        $prospectList = null;
        $prospectCount = 0;
        $leads = null;

        if (! $viewer->isSeat() && $offer->user_id === $viewer->id && $offer->status === 'ready') {
            $prospectList = CrmList::forOffer($offer);
            $prospectCount = $prospectList->contacts()->count();
            $allowance = app(LeadAllowance::class);
            $leads = [
                'available' => app(LeadFinder::class)->isAvailable(),
                'run' => LeadRun::query()->where('offer_id', $offer->id)->latest('id')->first(),
                'free_remaining' => $allowance->freeRemaining($viewer),
                'cost_per_lead' => $allowance->costPerPaidLead(),
            ];
        }

        return [
            'offer' => $offer,
            'research' => new ResearchPresenter($offer, $viewer),
            'tiles' => OfferFeatures::tilesFor($viewer, $offer),
            'prospectList' => $prospectList,
            'prospectCount' => $prospectCount,
            'leads' => $leads,
        ];
    }
}
