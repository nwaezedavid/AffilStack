<?php

namespace App\Services\Modules;

use App\Jobs\RunXGeneration;
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
 * Feature 7 (Phase 3 backlog, item 7): X (Twitter) module. A single
 * deliverable — a thread with several alternative opening-tweet hooks, so
 * the user can pick (or A/B test) whichever hook fits their audience,
 * rather than regenerating the whole thread to try a different opener.
 * Content only, same as LinkedIn — nothing here posts on the user's behalf.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows.
 */
class XService
{
    protected const MODULE = 'x_thread';

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

        RunXGeneration::dispatch($generation);

        return $generation;
    }

    public function thread(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are an X (Twitter) growth writer known for threads that get read
            to the end and drive clicks without sounding like an ad. Threads win
            on a strong, specific first line — never a generic "Let's talk about X".

            Return a JSON object with exactly these keys:
            - "hook_variants": array of 4-5 alternative opening-tweet options for tweet 1, each a complete standalone tweet under 280 characters, each testing a different angle (a bold claim, a specific number, a contrarian take, a question, a mini-story opener). These are alternatives to try — not all used at once.
            - "thread": array of 6-10 tweet objects in posting order, each with "position" (integer) and "text" (under 280 characters). Tweet 1's text should be the strongest of the hook_variants. The body builds the case with specific, concrete detail (no vague hype), and the final tweet is a clear call to action using the placeholder "{{AFFILIATE_LINK}}" (mention the link is in the next reply, since X doesn't reliably show clickable links well inside a thread body).
            - "best_posting_time": one specific recommendation, e.g. "Weekday 8-9am in the buyer's timezone".
            PROMPT;

        $this->run($generation, $system, $this->offerContext($generation->offer));
    }

    /**
     * Called by RunXGeneration inside the queue worker. Always leaves the
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
            Log::error('Unexpected error generating an X thread', ['generation_id' => $generation->id, 'error' => $e->getMessage()]);
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
            Log::error('Credit spend failed after a successful X thread generation — content was generated but not charged', [
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
