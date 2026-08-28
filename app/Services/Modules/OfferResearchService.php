<?php

namespace App\Services\Modules;

use App\Jobs\RunOfferResearch;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;

/**
 * Feature 1: given nothing but a product name, its URL, and the affiliate
 * network it's on, work out who actually wants it, where to find them, and
 * the single best channel to promote it through.
 *
 * Runs in the background: queue() does the fast synchronous part (credit
 * gate + a placeholder Offer row) and dispatches a job; research() is the
 * slow part (the actual AI call) that the queue worker calls.
 */
class OfferResearchService
{
    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, string $productName, string $productUrl, string $affiliateNetwork): Offer
    {
        $cost = (int) config('credits.costs.research');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run offer research.");
        }

        $offer = Offer::create([
            'user_id' => $user->id,
            'product_name' => $productName,
            'product_url' => $productUrl,
            'affiliate_network' => $affiliateNetwork,
            'status' => 'queued',
        ]);

        RunOfferResearch::dispatch($offer);

        return $offer;
    }

    /**
     * Called by RunOfferResearch inside the queue worker. Always leaves the
     * offer in a terminal state (ready/failed) and never throws — the job
     * reads the final status to decide which notification to send.
     */
    public function research(Offer $offer): void
    {
        $user = $offer->user;
        $cost = (int) config('credits.costs.research');

        if (! $this->credits->hasEnough($user, $cost)) {
            $offer->update(['status' => 'failed']);
            $offer->generations()->create([
                'user_id' => $user->id,
                'module' => 'research',
                'input' => $offer->only(['product_name', 'product_url', 'affiliate_network']),
                'credits_spent' => 0,
                'status' => 'failed',
                'error_message' => 'Insufficient credits at processing time.',
            ]);

            return;
        }

        $system = <<<'PROMPT'
            You are a senior affiliate marketing strategist who has personally launched
            hundreds of affiliate campaigns across LinkedIn, blogs, YouTube, UGC/short-form
            video, Pinterest, and local/B2B outreach. You explain things clearly enough for
            a total beginner to act on today, while still being sharp and specific enough
            for an experienced affiliate to trust the recommendation.

            Given only a product name, its official URL, and the affiliate network it is
            listed on, produce a research brief a real affiliate could execute today.

            Return a JSON object with exactly these keys:
            - "ideal_customer_summary": 2-3 sentences describing the ideal buyer (role, company size or life stage, budget, what pain drives them to search for this).
            - "pain_points": array of 3-5 short strings, the specific problems this buyer has that the product solves.
            - "where_to_find": array of 4-6 short strings naming concrete, specific places to find this buyer (named subreddits, named Facebook/LinkedIn groups, named forums, search terms, hashtags, or event/community types — not generic advice like "social media").
            - "recommended_channel": one of "linkedin", "blog", "youtube", "ugc", "pinterest", "google_maps".
            - "recommended_channel_reason": 2-3 sentences on why that channel beats the other five for this specific product and buyer.
            - "recommended_angle": one sharp promotional angle/hook a total beginner could copy today.
            - "secondary_channel": one of the same six channel values, the second-best option.
            PROMPT;

        $userPrompt = "Product name: {$offer->product_name}\nOfficial URL: {$offer->product_url}\nAffiliate network: {$offer->affiliate_network}";

        try {
            $result = $this->ai->generateJson($system, $userPrompt);
        } catch (AIGenerationException $e) {
            $offer->update(['status' => 'failed']);
            $offer->generations()->create([
                'user_id' => $user->id,
                'module' => 'research',
                'input' => $offer->only(['product_name', 'product_url', 'affiliate_network']),
                'credits_spent' => 0,
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);

            return;
        }

        $offer->update([
            'status' => 'ready',
            'ideal_customer_summary' => $result['ideal_customer_summary'] ?? null,
            'where_to_find' => is_array($result['where_to_find'] ?? null)
                ? implode("\n", $result['where_to_find'])
                : ($result['where_to_find'] ?? null),
            'recommended_channel' => $result['recommended_channel'] ?? null,
            'recommended_angle' => $result['recommended_angle'] ?? null,
            'research_data' => $result,
        ]);

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'research',
            'input' => $offer->only(['product_name', 'product_url', 'affiliate_network']),
            'output' => json_encode($result),
            'output_meta' => $result,
            'credits_spent' => $cost,
            'status' => 'completed',
        ]);

        $this->credits->spend($user, $cost, 'research', $generation);
    }
}
