<?php

namespace App\Services\Modules;

use App\Jobs\RunUgcGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Feature 5: UGC module. Two steps, but unlike YouTube's linear
 * script-then-metadata flow, the second step depends on a choice the user
 * makes in between: first the AI recommends several UGC angles (formats
 * like unboxing, before/after, problem-solution testimonial) for the offer,
 * then the user picks ONE of those angles and only that angle gets turned
 * into a full script plus a per-platform posting pack (best platforms, each
 * with its own title/caption/tags).
 *
 * The chosen angle is referenced by (angles_generation_id, angle_index)
 * rather than just "the offer's latest angles" — a user can re-roll angles
 * more than once, and content generation must know exactly which round and
 * which option they picked, not guess from recency.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split.
 */
class UgcService
{
    /** @var array<string, string> */
    protected const MODULES = [
        'angles' => 'ugc_angles',
        'content' => 'ugc_content',
    ];

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    /**
     * @param  array{angles_generation_id?: int, angle_index?: int}|null  $context
     */
    public function queue(User $user, Offer $offer, string $method, ?array $context = null): Generation
    {
        $module = self::MODULES[$method] ?? null;

        if (! $module) {
            throw new InvalidArgumentException("Unknown UGC generation method [{$method}].");
        }

        $input = ['offer_id' => $offer->id];

        if ($method === 'content') {
            $anglesGenerationId = $context['angles_generation_id'] ?? null;
            $angleIndex = $context['angle_index'] ?? null;

            if ($anglesGenerationId === null || $angleIndex === null || ! $this->resolveAngle($offer, $anglesGenerationId, $angleIndex)) {
                throw new RuntimeException('Pick one of the recommended UGC angles first — content is written to match a specific angle.');
            }

            $input['angles_generation_id'] = $anglesGenerationId;
            $input['angle_index'] = $angleIndex;
        }

        $cost = (int) config("credits.costs.{$module}");

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run {$module}.");
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => $module,
            'input' => $input,
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunUgcGeneration::dispatch($generation, $method);

        return $generation;
    }

    public function angles(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a UGC (user-generated content) strategist who helps affiliate
            marketers pick the right creative angle before they film anything.

            Return a JSON object with exactly one key, "angles", an array of 5
            objects, each with:
            - "name": a short label for the angle, e.g. "Before/After Transformation".
            - "format": the UGC format this is, e.g. "unboxing", "day-in-the-life", "problem-solution testimonial", "myth-busting reaction", "tutorial/how-to", "relatable rant".
            - "hook_idea": the exact opening line or first visual beat that would stop someone scrolling in the first 2 seconds.
            - "why_it_works": one sentence on why this specific angle fits this offer and its ideal customer.

            Vary the 5 angles meaningfully — do not give five versions of the same idea.
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    public function content(Generation $generation): void
    {
        $offer = $generation->offer;
        $anglesGenerationId = $generation->input['angles_generation_id'] ?? null;
        $angleIndex = $generation->input['angle_index'] ?? null;
        $angle = ($anglesGenerationId !== null && $angleIndex !== null)
            ? $this->resolveAngle($offer, $anglesGenerationId, $angleIndex)
            : null;

        if (! $angle) {
            $generation->update(['status' => 'failed', 'error_message' => 'The chosen UGC angle could not be found — it may have come from a since-regenerated angle list. Pick an angle again and retry.']);

            return;
        }

        $system = <<<'PROMPT'
            You are a UGC creator and short-form platform strategist. You've already
            been given ONE specific creative angle for this affiliate offer — write
            the actual content for that angle, then advise how to distribute it.

            Return a JSON object with exactly these keys:
            - "script": the full UGC script in Markdown, written beat-by-beat for a single-person, phone-shot video (Hook, Body, Call to action), matching the given angle's format and hook. Conversational, first-person, not ad copy. Use the placeholder "{{AFFILIATE_LINK}}" wherever the script tells the viewer to find the link (bio link, comment, etc.) — at least once.
            - "on_screen_text_ideas": array of 4-6 short on-screen text/caption overlay ideas timed to key moments in the script.
            - "platforms": array of 3-4 objects, each the best-fit platform for this specific angle, with: "platform" (e.g. "TikTok", "Instagram Reels", "YouTube Shorts", "Facebook Reels"), "title" (a short on-platform title/hook text), "caption" (the full post caption, written in that platform's typical voice, with "{{AFFILIATE_LINK}}" placed naturally), "tags" (array of 8-15 relevant hashtags for that platform), and "posting_tip" (one specific, platform-native tip — ideal length, format quirk, or posting-time nuance for THIS platform).
            - "video_script": a clean, natural spoken-word script for a text-to-speech AI avatar to read aloud on camera — plain conversational sentences only, NO markdown, NO section headers or labels like "Hook:", NO camera directions or on-screen-text cues, roughly 120-170 words (about 45-60 seconds spoken). Weave in a brief, natural spoken mention that it's a partnership/affiliate link somewhere in the script, and close by pointing them to the link in the bio/description rather than saying a URL out loud.

            Rank "platforms" best-fit first.
            PROMPT;

        $userPrompt = $this->offerContext($offer)
            ."\nThe chosen UGC angle to write for (do not deviate from it):\n"
            ."Name: {$angle['name']}\n"
            ."Format: {$angle['format']}\n"
            ."Hook idea: {$angle['hook_idea']}\n"
            ."Why it works: {$angle['why_it_works']}\n";

        $this->run($generation, $system, $userPrompt);
    }

    /**
     * Called by RunUgcGeneration inside the queue worker. Always leaves the
     * generation in a terminal state and never throws.
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
            Log::error('Unexpected error generating UGC content', ['generation_id' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error_message' => 'Something went wrong generating this content — please try again.']);

            return;
        }

        // Spending the credits and marking the generation completed must be
        // atomic — see BlogArticleService for why.
        try {
            DB::transaction(function () use ($generation, $user, $module, $cost, $result) {
                $this->credits->spend($user, $cost, $module, $generation);

                $generation->update([
                    'output' => $result['script'] ?? json_encode($result),
                    'output_meta' => $result,
                    'credits_spent' => $cost,
                    'status' => 'completed',
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Credit spend failed after a successful UGC generation — content was generated but not charged', [
                'generation_id' => $generation->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);
        }
    }

    /**
     * Look up one specific recommended angle by the exact generation round
     * it came from plus its index in that round's list — never "the latest
     * angles" — so a re-rolled angle list can't silently change which angle
     * an already-picked content generation is written for.
     *
     * @return array{name: string, format: string, hook_idea: string, why_it_works: string}|null
     */
    protected function resolveAngle(Offer $offer, int $anglesGenerationId, int $angleIndex): ?array
    {
        $anglesGeneration = $offer->generations()
            ->where('id', $anglesGenerationId)
            ->where('module', 'ugc_angles')
            ->where('status', 'completed')
            ->first();

        return $anglesGeneration->output_meta['angles'][$angleIndex] ?? null;
    }

    protected function offerContext(Offer $offer): string
    {
        return "Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."Angle to use: {$offer->recommended_angle}\n";
    }
}
