<?php

namespace App\Services\Modules;

use App\Jobs\RunPinterestGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;

/**
 * Core feature 6 (Phase 2): Pinterest pin generator. Pinterest is a search
 * and discovery engine as much as a social feed, so unlike a single X
 * thread or TikTok script, a pin's title/description/keywords need to be
 * genuinely different across a few variants to test which one Pinterest's
 * own search surfaces best — the same "give me alternatives to try" shape
 * item 7's X hook_variants already established, applied here to the whole
 * pin rather than just the opening line.
 *
 * AffilStack has no Pinterest publishing integration (same "content only,
 * user copies/pastes it themselves" rule as every other channel — see the
 * plan's platform-limits section) so this produces the pin copy and an
 * image prompt for the user's own image generator, not a published pin.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows.
 */
class PinterestService
{
    protected const MODULE = 'pinterest_pin';

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

        RunPinterestGeneration::dispatch($generation);

        return $generation;
    }

    public function pins(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a Pinterest marketing specialist who writes pins that win
            Pinterest's own search and recommendation surfaces, not just a
            pretty caption — Pinterest is a visual search engine, so keyword
            placement in the title and description matters as much as the hook.

            Return a JSON object with exactly these keys:
            - "pins": array of 3 alternative pin variants to test, each an object with:
              - "title": under 100 characters, keyword-forward (how someone would search for this), not clickbait.
              - "description": 300-500 characters, natural sentences (not a keyword list) that front-load the main keyword phrase in the first sentence, end with a clear reason to tap through, and include the placeholder "{{AFFILIATE_LINK}}" once as the call to action.
              - "image_prompt": a detailed prompt for an external image generator describing a vertical (2:3 ratio) pin image — composition, text overlay suggestion, color/style — for someone to actually generate the pin's image with.
              - "alt_text": under 500 characters, a plain factual description of the pin image for accessibility (not a second caption).
            - "board_suggestion": the type of Pinterest board this pin belongs on (e.g. "a board about budget home organization"), not a literal board name to create.
            - "keywords": array of 8-12 search keywords/phrases relevant to this product on Pinterest, for use in the pin's own profile/board descriptions.
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    /**
     * Called by RunPinterestGeneration inside the queue worker. Always
     * leaves the generation in a terminal state and never throws.
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
        }

        $generation->update([
            'output' => json_encode($result),
            'output_meta' => $result,
            'credits_spent' => $cost,
            'status' => 'completed',
        ]);

        $this->credits->spend($user, $cost, $module, $generation);
    }

    protected function offerContext(Offer $offer): string
    {
        return "Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."Angle to use: {$offer->recommended_angle}\n";
    }
}
