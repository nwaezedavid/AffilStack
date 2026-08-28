<?php

namespace App\Services\Modules;

use App\Jobs\RunTikTokGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;

/**
 * Feature 7 (Phase 3 backlog, item 7): TikTok module. Generates a short-form
 * video package directly from the offer — script, on-screen text cues, and
 * caption — in one step. Distinct from the UGC module's per-platform posting
 * pack (which repurposes a UGC angle across several ranked platforms): this
 * is a purpose-built TikTok pipeline that doesn't require running UGC first.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows.
 */
class TikTokService
{
    protected const MODULE = 'tiktok_video';

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

        RunTikTokGeneration::dispatch($generation);

        return $generation;
    }

    public function video(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a TikTok creator who makes affiliate/product videos that don't
            feel like ads — fast-paced, native to the platform, hook in the first
            2 seconds or the viewer scrolls past.

            Return a JSON object with exactly these keys:
            - "script": the full spoken/visual script in Markdown, "**[0:00]**"-style timestamp markers before each beat (Hook, Problem, Product/demo, Proof or objection handled, CTA). 25-60 seconds of spoken content (roughly 60-140 spoken words) — TikTok punishes anything that drags. Use the placeholder "{{AFFILIATE_LINK}}" wherever the script tells the viewer to check the link, and note in the script that it's "link in bio" since TikTok captions don't support clickable links for most accounts.
            - "on_screen_text": array of 4-8 objects, each with "timing" (e.g. "0:00-0:02") and "text" (the on-screen text overlay for that moment — short, punchy, matches what's being said).
            - "caption": the TikTok caption — a short, native-sounding hook line (not a repeat of the video hook word-for-word), then the placeholder "{{AFFILIATE_LINK}}" with a "link in bio" note, then 3-5 relevant hashtags mixing broad and niche.
            - "hashtags": array of the same 3-5 hashtags used in the caption, without the "#", for easy reuse elsewhere.
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    /**
     * Called by RunTikTokGeneration inside the queue worker. Always leaves
     * the generation in a terminal state and never throws.
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
            'output' => $result['script'] ?? json_encode($result),
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
