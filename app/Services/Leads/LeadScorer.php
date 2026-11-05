<?php

namespace App\Services\Leads;

use App\Models\Offer;
use App\Services\AI\AIProvider;
use App\Support\ResearchPresenter;
use Throwable;

/**
 * Rates how well each found lead fits a product's buyer (0–100) with a
 * one-line reason, using the product's own research as the buyer profile.
 * Falls back to a simple rule-based score if the AI call fails, so a run
 * never loses its leads over a scoring hiccup.
 */
class LeadScorer
{
    protected const BATCH = 25;

    public function __construct(protected AIProvider $ai) {}

    /**
     * @param  array<int, array<string, mixed>>  $leads
     * @return array<int, array{score: int, reason: string}> keyed like $leads
     */
    public function score(?Offer $offer, array $leads): array
    {
        $scores = [];

        foreach (array_chunk($leads, self::BATCH, true) as $batch) {
            $scores += $this->scoreBatch($offer, $batch);
        }

        return $scores;
    }

    /**
     * @param  array<int, array<string, mixed>>  $batch
     * @return array<int, array{score: int, reason: string}>
     */
    protected function scoreBatch(?Offer $offer, array $batch): array
    {
        $fallback = collect($batch)->map(fn (array $lead): array => $this->ruleScore($offer, $lead))->all();

        if (! $offer) {
            return $fallback;
        }

        $research = new ResearchPresenter($offer);
        $profile = implode("\n", array_filter([
            'Product: '.$offer->product_name.' ('.$offer->product_url.')',
            $research->idealCustomer() ? 'Ideal customer: '.$research->idealCustomer() : null,
            $research->whoNeedsIt()['businesses'] !== [] ? 'Businesses that need it: '.implode('; ', $research->whoNeedsIt()['businesses']) : null,
            $research->linkedinKeywords()['people'] !== [] ? 'Decision-maker titles: '.implode('; ', $research->linkedinKeywords()['people']) : null,
        ]));

        $rows = collect($batch)->map(fn (array $lead, int $index): string => json_encode([
            'index' => $index,
            'company' => $lead['company'] ?? null,
            'title' => $lead['title'] ?? null,
            'business_type' => $lead['type'] ?? null,
            'location' => $lead['location'] ?? null,
            'website' => $lead['website'] ?? null,
            'has_email' => filled($lead['email'] ?? null),
        ]))->implode("\n");

        $system = <<<'PROMPT'
            You qualify B2B sales leads for an affiliate marketer. For each lead, judge how likely
            this business or person is to buy the product described, from the buyer profile given.
            Return JSON: {"scores": [{"index": <number>, "score": <0-100>, "reason": "<max 15 words>"}]}
            with one entry per lead. 80+ = clear fit, 50-79 = possible fit, below 50 = weak fit.
            Judge only on the fields given; never invent facts about a lead.
            PROMPT;

        try {
            $result = $this->ai->generateJson($system, "Buyer profile:\n{$profile}\n\nLeads (one JSON object per line):\n{$rows}");
        } catch (Throwable) {
            return $fallback;
        }

        foreach ((array) ($result['scores'] ?? []) as $row) {
            $index = is_array($row) ? ($row['index'] ?? null) : null;

            if (is_numeric($index) && array_key_exists((int) $index, $fallback)) {
                $fallback[(int) $index] = [
                    'score' => max(0, min(100, (int) ($row['score'] ?? 0))),
                    'reason' => mb_substr(trim((string) ($row['reason'] ?? '')), 0, 160),
                ];
            }
        }

        return $fallback;
    }

    /**
     * @param  array<string, mixed>  $lead
     * @return array{score: int, reason: string}
     */
    public function ruleScore(?Offer $offer, array $lead): array
    {
        $score = 40;
        $reasons = [];

        if (filled($lead['email'] ?? null)) {
            $score += 20;
            $reasons[] = 'has a business email';
        }

        if (filled($lead['website'] ?? null)) {
            $score += 10;
        }

        if ($offer && filled($lead['title'] ?? null)) {
            $titles = (new ResearchPresenter($offer))->linkedinKeywords()['people'];
            $title = mb_strtolower((string) $lead['title']);

            if (collect($titles)->contains(fn (string $wanted): bool => str_contains($title, mb_strtolower($wanted)) || str_contains(mb_strtolower($wanted), $title))) {
                $score += 20;
                $reasons[] = 'decision-maker title';
            }
        }

        if (filled($lead['keyword'] ?? null)) {
            $score += 10;
            $reasons[] = 'matches "'.$lead['keyword'].'"';
        }

        return ['score' => min(100, $score), 'reason' => ucfirst(implode(', ', $reasons) ?: 'Matches the product\'s buyer search')];
    }
}
