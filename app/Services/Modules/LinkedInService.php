<?php

namespace App\Services\Modules;

use App\Jobs\RunLinkedInGeneration;
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
use Throwable;

/**
 * Feature 2: LinkedIn marketing module. Every method here produces content
 * only — nothing sends a connection request, a DM, or a post on the user's
 * behalf. LinkedIn does not allow third-party automated outreach without a
 * Marketing Partner agreement, so keeping this content-only protects every
 * user's LinkedIn account from being flagged or suspended.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split.
 */
class LinkedInService
{
    /** @var array<string, string> */
    protected const MODULES = [
        'keywords' => 'linkedin_keywords',
        'dmSequence' => 'linkedin_dm_sequence',
        'post' => 'linkedin_post',
        'article' => 'linkedin_article',
    ];

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Offer $offer, string $method): Generation
    {
        $module = self::MODULES[$method] ?? null;

        if (! $module) {
            throw new InvalidArgumentException("Unknown LinkedIn generation method [{$method}].");
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

        RunLinkedInGeneration::dispatch($generation, $method);

        return $generation;
    }

    public function keywords(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a LinkedIn organic growth strategist. Given an affiliate offer,
            find the best LinkedIn search keywords and Boolean strings to locate the
            ideal buyer using LinkedIn's people search and Sales Navigator-style filters.

            Return a JSON object with exactly these keys:
            - "search_keywords": array of 6-10 short keyword phrases to use in LinkedIn people search.
            - "boolean_search_strings": array of 2-3 ready-to-paste Boolean search strings combining job titles/keywords.
            - "job_titles": array of 5-8 specific job titles to filter by.
            - "industries": array of 4-6 LinkedIn industry filter values.
            - "groups_to_join": array of 3-5 realistic LinkedIn Group name patterns/types worth joining.
            PROMPT;

        $this->run($generation, $system);
    }

    public function dmSequence(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a LinkedIn outbound copywriter who writes DMs people actually
            reply to — short, personal-sounding, no hard pitch in message 1.

            Return a JSON object with exactly this key:
            - "messages": array of exactly 4 objects, each with:
              - "step": integer 1-4
              - "send_timing": when to send relative to the previous step, e.g. "Day 0 — right after connecting" or "Day 3 if no reply"
              - "goal": one short phrase, the purpose of this message
              - "message": the actual message text, under 500 characters, with "{{first_name}}" as a placeholder

            Message 1 is a connection note. Messages 2-4 are progressively lower-pressure
            follow-ups, ending with a soft "should I close this out?" style breakup message.
            PROMPT;

        $this->run($generation, $system);
    }

    public function post(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a LinkedIn content creator known for posts that stop the scroll
            and drive clicks without sounding like an ad.

            Return a JSON object with exactly these keys:
            - "post_text": the full LinkedIn post, under 1300 characters, hook as line 1, line breaks for readability, a clear soft CTA using the placeholder "{{AFFILIATE_LINK}}" (LinkedIn posts read better with the link in the first comment, so mention that naturally rather than pasting a raw URL inline), 3-5 relevant hashtags at the end.
            - "image_prompt": a detailed prompt (for an AI image generator) describing a scroll-stopping image to pair with this post.
            - "best_posting_time": one specific recommendation, e.g. "Tuesday 8-10am in the buyer's timezone".
            PROMPT;

        $this->run($generation, $system);
    }

    public function article(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a LinkedIn thought-leadership writer. Write a LinkedIn Article
            (the long-form publishing format) that builds credibility and naturally
            leads into recommending this product, without reading like an ad.

            Return a JSON object with exactly these keys:
            - "headline": under 100 characters.
            - "article_markdown": 500-800 words in Markdown, subheadings encouraged, ends with a clear CTA using the placeholder "{{AFFILIATE_LINK}}".
            - "suggested_cover_image_prompt": a detailed AI image prompt for the article's cover image.
            PROMPT;

        $this->run($generation, $system);
    }

    /**
     * Called by RunLinkedInGeneration inside the queue worker. Always leaves
     * the generation in a terminal state and never throws.
     */
    protected function run(Generation $generation, string $system): void
    {
        $offer = $generation->offer;
        $user = $generation->user;
        $module = $generation->module;
        $cost = (int) config("credits.costs.{$module}");

        if (! $this->credits->hasEnough($user, $cost)) {
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);

            return;
        }

        try {
            $result = $this->ai->generateJson($system, $this->offerContext($offer));
        } catch (AIGenerationException $e) {
            $generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::error('Unexpected error generating LinkedIn content', ['generation_id' => $generation->id, 'error' => $e->getMessage()]);
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
            Log::error('Credit spend failed after a successful LinkedIn generation — content was generated but not charged', [
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
            ."Angle to use: {$offer->recommended_angle}\n";
    }
}
