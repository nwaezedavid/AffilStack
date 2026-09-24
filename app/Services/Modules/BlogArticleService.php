<?php

namespace App\Services\Modules;

use App\Jobs\RunBlogArticle;
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
 * Feature 3: one-click blog / Medium article generation, formatted to rank
 * (SEO) and formatted to convert (affiliate CTA placement), built from an
 * already-researched offer. Runs in the background — see OfferResearchService
 * for the same queue()/run() split.
 */
class BlogArticleService
{
    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Offer $offer, ?string $targetKeyword = null): Generation
    {
        $cost = (int) config('credits.costs.blog_article');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to generate a blog article.");
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => 'blog_article',
            'input' => ['offer_id' => $offer->id, 'target_keyword' => $targetKeyword],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunBlogArticle::dispatch($generation);

        return $generation;
    }

    /**
     * Called by RunBlogArticle inside the queue worker. Always leaves the
     * generation in a terminal state and never throws.
     */
    public function generate(Generation $generation): void
    {
        $offer = $generation->offer;
        $user = $generation->user;
        $targetKeyword = $generation->input['target_keyword'] ?? null;
        $cost = (int) config('credits.costs.blog_article');

        if (! $this->credits->hasEnough($user, $cost)) {
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);

            return;
        }

        $system = <<<'PROMPT'
            You are an affiliate content writer and SEO editor. You write long-form
            articles that rank in search AND convert readers into affiliate clicks —
            never just a generic listicle. Every article follows this shape: a hook
            that names the reader's exact problem, a credibility/context section, a
            clear benefit-driven walkthrough of the product, honest handling of one
            objection, and a direct call to action using the affiliate link placeholder
            "{{AFFILIATE_LINK}}" at least twice (once mid-article after value is proven,
            once in the conclusion).

            Return a JSON object with exactly these keys:
            - "title": SEO-friendly, curiosity-driven, under 65 characters.
            - "slug": url-safe slug for the title.
            - "meta_description": under 155 characters, includes the target keyword naturally.
            - "target_keyword": the primary keyword this article targets.
            - "article_markdown": the full article in Markdown, 900-1400 words, with H2/H3 subheadings, short paragraphs, and the affiliate link placeholder used as described above.
            - "suggested_tags": array of 4-6 short tag strings.
            PROMPT;

        $userPrompt = "Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."Angle to use: {$offer->recommended_angle}\n"
            .($targetKeyword ? "Target keyword to prioritize: {$targetKeyword}\n" : '');

        try {
            $result = $this->ai->generateJson($system, $userPrompt);
        } catch (AIGenerationException $e) {
            $generation->update(['status' => 'failed', 'error_message' => $e->getMessage()]);

            return;
        } catch (Throwable $e) {
            Log::error('Unexpected error generating a blog article', ['generation_id' => $generation->id, 'error' => $e->getMessage()]);
            $generation->update(['status' => 'failed', 'error_message' => 'Something went wrong generating this content — please try again.']);

            return;
        }

        // Spending the credits and marking the generation completed must be
        // atomic: if spend() fails (e.g. a concurrent generation already
        // took the user's last credits between the hasEnough() check above
        // and here), the generation must NOT be left "completed" with
        // credits_spent recorded — that would hand out this AI-generated
        // article for free while the ledger shows nothing charged.
        try {
            DB::transaction(function () use ($generation, $user, $cost, $result) {
                $this->credits->spend($user, $cost, 'blog_article', $generation);

                $generation->update([
                    'output' => $result['article_markdown'] ?? null,
                    'output_meta' => $result,
                    'credits_spent' => $cost,
                    'status' => 'completed',
                ]);
            });
        } catch (Throwable $e) {
            Log::error('Credit spend failed after a successful blog article generation — content was generated but not charged', [
                'generation_id' => $generation->id, 'user_id' => $user->id, 'error' => $e->getMessage(),
            ]);
            $generation->update(['status' => 'failed', 'error_message' => 'Insufficient credits at processing time.']);
        }
    }
}
