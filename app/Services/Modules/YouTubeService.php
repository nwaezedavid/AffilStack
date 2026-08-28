<?php

namespace App\Services\Modules;

use App\Jobs\RunYouTubeGeneration;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use InvalidArgumentException;
use RuntimeException;

/**
 * Feature 4: YouTube module. Two steps, run in that order: a video script,
 * then a metadata package (title options, description, keywords, tags,
 * category, thumbnail prompt) written to match the script that was actually
 * generated — not just the offer in isolation. Like LinkedIn's image_prompt
 * fields, the thumbnail is a text prompt for the user to run through an
 * image generator of their choice, not an image AffiliStack creates itself.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split.
 */
class YouTubeService
{
    /** @var array<string, string> */
    protected const MODULES = [
        'script' => 'youtube_script',
        'metadata' => 'youtube_metadata',
    ];

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Offer $offer, string $method): Generation
    {
        $module = self::MODULES[$method] ?? null;

        if (! $module) {
            throw new InvalidArgumentException("Unknown YouTube generation method [{$method}].");
        }

        if ($method === 'metadata' && ! $this->latestScript($offer)) {
            throw new RuntimeException('Generate the video script first — metadata is written to match it.');
        }

        $cost = (int) config("credits.costs.{$module}");

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run {$module}.");
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => $module,
            'input' => ['offer_id' => $offer->id],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunYouTubeGeneration::dispatch($generation, $method);

        return $generation;
    }

    public function script(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a YouTube scriptwriter who specializes in affiliate/product
            review-style videos that keep viewers watching and drive clicks without
            feeling like an ad. Write for a spoken-aloud video, not an article.

            Return a JSON object with exactly these keys:
            - "working_title": a rough title for reference only (a separate step writes the real SEO title later).
            - "hook": the first ~15 seconds of spoken script — must earn the click by naming the viewer's problem or promising a specific outcome, no throat-clearing.
            - "script_markdown": the full spoken script in Markdown, with "**[MM:SS]**"-style rough timestamp markers before each new section (Hook, Intro, Main content broken into clear sections, Objection/honest caveat, Call to action). 4-7 minutes of spoken content (roughly 600-1000 spoken words). Use the placeholder "{{AFFILIATE_LINK}}" wherever the script tells the viewer to click the link, at least once mid-video and once in the outro.
            - "b_roll_suggestions": array of 5-8 short b-roll/visual ideas tied to specific moments in the script.
            - "estimated_length_minutes": a number, the rough spoken runtime.
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    public function metadata(Generation $generation): void
    {
        $offer = $generation->offer;
        $script = $this->latestScript($offer);

        if (! $script) {
            $generation->update(['status' => 'failed', 'error_message' => 'No completed video script found for this offer — generate the script first.']);

            return;
        }

        $system = <<<'PROMPT'
            You are a YouTube SEO specialist. Given an affiliate offer and the video
            script that was already written for it, produce metadata that matches
            what the video actually says — don't invent claims the script doesn't make.

            Return a JSON object with exactly these keys:
            - "title_options": array of 5 click-worthy titles, each under 70 characters, varying the angle (curiosity, benefit, question, etc.).
            - "description": the full YouTube video description — a 2-3 sentence hook, then a short bullet-style summary of what the video covers, the placeholder "{{AFFILIATE_LINK}}" on its own line with a one-line call to action, and 3-5 relevant hashtags at the end.
            - "keywords": array of 10-15 SEO search-intent phrases viewers would type in to find this video.
            - "tags": array of 15-25 short YouTube video tags (single words or short phrases, no hashtags), keeping the combined length under roughly 450 characters since that's YouTube's real tag-box limit.
            - "category": the single best-fit standard YouTube category name (e.g. "Howto & Style", "Education", "Science & Technology", "People & Blogs").
            - "thumbnail_prompt": a detailed prompt for an AI image generator describing a high-contrast, scroll-stopping thumbnail that accurately represents the video.
            PROMPT;

        $userPrompt = $this->offerContext($offer)
            ."\nVideo script this metadata must match:\n{$script->output}\n";

        $this->run($generation, $system, $userPrompt);
    }

    /**
     * Called by RunYouTubeGeneration inside the queue worker. Always leaves
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
            'output' => $result['script_markdown'] ?? json_encode($result),
            'output_meta' => $result,
            'credits_spent' => $cost,
            'status' => 'completed',
        ]);

        $this->credits->spend($user, $cost, $module, $generation);
    }

    protected function latestScript(Offer $offer): ?Generation
    {
        return $offer->generations()
            ->where('module', 'youtube_script')
            ->where('status', 'completed')
            ->latest()
            ->first();
    }

    protected function offerContext(Offer $offer): string
    {
        return "Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."Angle to use: {$offer->recommended_angle}\n";
    }
}
