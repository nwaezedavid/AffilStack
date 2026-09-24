<?php

namespace App\Services\Modules;

use App\Jobs\RunCompetitorAngleGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Feature 4 (Phase 3 backlog, item 4): competitor angle scanner. AffilStack
 * has no live web-browsing or scraping capability — every module, this one
 * included, calls the AI provider directly with just the offer's context
 * (see the plan's "AI provider" cross-cutting note) — so this surfaces the
 * common promotional patterns affiliates in this space tend to reach for,
 * from the model's own knowledge, rather than a scrape of today's actual
 * live competitor posts. The point isn't a real-time competitor feed; it's
 * making sure a user's recommended angle (from feature 1's offer research)
 * isn't accidentally the same generic angle everyone else already used.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows.
 */
class CompetitorAngleService
{
    protected const MODULE = 'competitor_angles';

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Offer $offer): Generation
    {
        $cost = (int) config('credits.costs.'.self::MODULE);

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run ".self::MODULE.'.');
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => self::MODULE,
            'input' => ['offer_id' => $offer->id],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunCompetitorAngleGeneration::dispatch($generation);

        return $generation;
    }

    public function scan(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a competitive intelligence analyst for affiliate marketers.
            For a given product, most affiliates promoting it converge on a small
            handful of overused angles — you know this space well enough to name
            those patterns specifically, not generically.

            Return a JSON object with exactly these keys:
            - "common_angles": array of 4-6 objects, each with:
              - "angle_name": a short label, e.g. "Problem-Agitate-Solve" or "Before/After Transformation".
              - "example_headline": one realistic headline or hook written in that exact style, specific to THIS product (not a generic placeholder).
              - "typical_channel": where this angle is most commonly seen (e.g. "TikTok", "blog SEO content", "YouTube reviews", "Instagram Reels").
              - "why_it_works": one sentence.
              - "saturation": "low", "medium", or "high" — how overused this specific angle already is for this specific product.
            - "differentiation_opportunity": 2-3 sentences naming something true and compelling about this product that most affiliates are NOT leading with — a real gap, not a vague "be authentic" platitude.
            - "recommended_adjustment": one concrete sentence on how to sharpen this offer's existing recommended angle (given in the context below) in light of which common angles are already saturated.
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    /**
     * Called by RunCompetitorAngleGeneration inside the queue worker.
     * Always leaves the generation in a terminal state and never throws.
     */
    protected function run(Generation $generation, string $system, string $userPrompt): void
    {
        $user = $generation->user;
        $module = $generation->module;
        $cost = (int) config("credits.costs.{$module}");

        if (! $this->credits->hasEnough($user, $cost)) {
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);

            return;
        }

        try {
            $result = $this->ai->generateJson($system, $userPrompt);
        } catch (AIGenerationException $e) {
            $generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::error('Unexpected error scanning competitor angles', ['generation_id' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error_message' => 'Something went wrong generating this content — please try again.']);

            return;
        }

        // Spending the credits and marking the generation completed must be
        // atomic — see BlogArticleService for why.
        try {
            DB::transaction(function () use ($generation, $user, $module, $cost, $result) {
                $this->credits->spend($user, $cost, $module, $generation);

                $generation->update([
                    'output' => json_encode($result),
                    'output_meta' => $result,
                    'credits_spent' => $cost,
                    'status' => 'completed',
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Credit spend failed after a successful competitor angle generation — content was generated but not charged', [
                'generation_id' => $generation->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);
        }
    }

    protected function offerContext(Offer $offer): string
    {
        return "Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."This affiliate's current recommended angle: {$offer->recommended_angle}\n";
    }
}
