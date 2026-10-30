<?php

namespace App\Services\Agents\Customer;

use App\Models\AgentActivity;
use App\Models\NetworkProgram;
use App\Models\Offer;
use App\Models\User;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Modules\OfferResearchService;
use RuntimeException;

/**
 * Carries out an agent suggestion the customer approved on the AI Agents
 * page. Every approval re-checks ownership — the activity's meta is never
 * trusted on its own.
 *
 * @throws RuntimeException with a message for the customer
 */
class AgentApprovals
{
    public function __construct(protected OfferResearchService $research) {}

    public function approve(User $user, AgentActivity $activity): string
    {
        if ($activity->user_id !== $user->id || $activity->status !== AgentActivity::STATUS_NEEDS_APPROVAL) {
            throw new RuntimeException('That suggestion is no longer waiting for approval.');
        }

        $message = match ($activity->meta['approval'] ?? null) {
            'swap_link' => $this->swapLink($user, $activity),
            'research_program' => $this->researchProgram($user, $activity),
            default => throw new RuntimeException('That suggestion can\'t be approved automatically.'),
        };

        $activity->update(['status' => AgentActivity::STATUS_APPROVED]);

        return $message;
    }

    protected function swapLink(User $user, AgentActivity $activity): string
    {
        $offer = Offer::query()->where('user_id', $user->id)->findOrFail((int) ($activity->meta['offer_id'] ?? 0));
        $useNetwork = (bool) ($activity->meta['use_network_link'] ?? false);

        if ($useNetwork && blank($offer->network_link) || ! $useNetwork && blank($offer->affiliate_link)) {
            throw new RuntimeException('That link has been removed since — update the product\'s links instead.');
        }

        $offer->update(['use_network_link' => $useNetwork]);

        return "Done — every tracked link for {$offer->product_name} now goes to your working ".($useNetwork ? 'network link' : 'affiliate link').'.';
    }

    protected function researchProgram(User $user, AgentActivity $activity): string
    {
        $program = NetworkProgram::query()
            ->whereIn('network_connection_id', $user->networkConnections()->pluck('id'))
            ->findOrFail((int) ($activity->meta['program_id'] ?? 0));

        $url = $program->homepage_url ?: $program->tracking_url;

        if (! $url) {
            throw new RuntimeException('This program has no website on the network, so it can\'t be researched automatically.');
        }

        try {
            $offer = $this->research->queue($user, $program->name, $url, ucfirst((string) $program->connection->network), $program->tracking_url);
        } catch (InsufficientCreditsException) {
            throw new RuntimeException('Not enough credits to research it. Buy a credit top-up or upgrade your plan.');
        }

        $offer->update(['network_program_id' => $program->id, 'network_link' => $program->tracking_url]);

        return "Researching {$program->name} now — it'll appear in My Products in about a minute.";
    }
}
