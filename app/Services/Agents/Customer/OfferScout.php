<?php

namespace App\Services\Agents\Customer;

use App\Models\AgentActivity;
use App\Models\NetworkProgram;
use App\Models\User;
use App\Notifications\AgentUpdate;
use App\Services\AI\AIProvider;
use App\Support\Agents\AgentSettings;
use Throwable;

/**
 * Offer Scout: once a week, compares the programs on the customer's
 * connected networks with what they promote today and suggests up to three
 * better-paying offers in their niche, with the reason and the likely
 * gain. Approving one researches it as a new product.
 */
class OfferScout
{
    public function __construct(protected AIProvider $ai) {}

    public function runFor(User $user): int
    {
        if (! AgentSettings::enabled($user, 'offer_scout')) {
            return 0;
        }

        $offers = $user->offers()->where('status', 'ready')->where('is_sandbox', false)->with('networkProgram')->latest()->take(10)->get();

        if ($offers->isEmpty()) {
            return 0;
        }

        $recentlySuggested = AgentActivity::query()->where('user_id', $user->id)->where('agent', 'offer_scout')
            ->where('created_at', '>=', now()->subDays(60))->get()->pluck('meta.program_id')->filter()->all();
        $promoted = $offers->pluck('network_program_id')->filter()->all();

        $programs = NetworkProgram::query()
            ->whereIn('network_connection_id', $user->networkConnections()->pluck('id'))
            ->whereIn('status', ['joined', 'available'])
            ->whereNotIn('id', array_merge($recentlySuggested, $promoted))
            ->latest('last_seen_at')
            ->take(60)
            ->get();

        if ($programs->isEmpty()) {
            return 0;
        }

        $current = $offers->map(fn ($offer): string => '- '.$offer->product_name.' ('.$offer->affiliate_network.')'
            .($offer->networkProgram?->commission ? ', commission '.$offer->networkProgram->commission : '')
            .($offer->ideal_customer_summary ? ', buyer: '.mb_substr($offer->ideal_customer_summary, 0, 160) : ''))->implode("\n");

        $candidates = $programs->map(fn (NetworkProgram $program): string => json_encode([
            'id' => $program->id,
            'name' => $program->name,
            'category' => $program->category,
            'commission' => $program->commission,
            'status' => $program->status,
        ]))->implode("\n");

        try {
            $result = $this->ai->generateJson(<<<'PROMPT'
                You are an affiliate marketing analyst. Compare the marketer's current products with programs
                available on their networks. Pick at most 3 programs that sell to the SAME kind of buyer and pay
                clearly more (higher % or bigger per-sale payout, or recurring vs one-off). Skip anything unrelated.
                Return JSON: {"suggestions": [{"id": <program id>, "reason": "<max 30 words>", "gain": "<max 15 words, e.g. 'about 2x per sale vs Systeme.io'>"}]}.
                Use {"suggestions": []} when nothing is clearly better. Never invent commission figures.
                PROMPT, "Current products:\n{$current}\n\nAvailable programs (one JSON per line):\n{$candidates}");
        } catch (Throwable $e) {
            report($e);

            return 0;
        }

        $suggested = 0;

        foreach (array_slice((array) ($result['suggestions'] ?? []), 0, 3) as $row) {
            $program = is_array($row) ? $programs->firstWhere('id', (int) ($row['id'] ?? 0)) : null;

            if (! $program) {
                continue;
            }

            $network = ucfirst((string) $program->connection?->network);
            $summary = "{$program->name} ({$network}) could pay you more: ".trim((string) ($row['gain'] ?? '')).'. '.trim((string) ($row['reason'] ?? ''));

            AgentSettings::log($user, 'offer_scout', 'suggested_offer', $summary, $program, [
                'program_id' => $program->id,
                'approval' => $program->status === 'joined' ? 'research_program' : null,
                'apply_url' => $program->status === 'joined' ? null : ($program->homepage_url ?: null),
            ], $program->status === 'joined' ? AgentActivity::STATUS_NEEDS_APPROVAL : AgentActivity::STATUS_DONE);
            $suggested++;
        }

        if ($suggested > 0) {
            $user->notify(new AgentUpdate('offer_scout', "Offer Scout found {$suggested} better-paying ".str('offer')->plural($suggested), 'See why they could earn you more, and research any of them with one tap.', route('agents.index')));
        }

        return $suggested;
    }
}
