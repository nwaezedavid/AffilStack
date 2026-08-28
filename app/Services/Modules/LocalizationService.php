<?php

namespace App\Services\Modules;

use App\Jobs\RunLocalizationGeneration;
use App\Models\Generation;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use InvalidArgumentException;

/**
 * Feature 8 (Phase 3 backlog, item 8): multi-market localization. Takes an
 * existing, already-generated piece of content and produces a new version
 * adapted for a different market — same JSON shape, same `module`, so the
 * localized copy renders through the exact same display block in
 * offers/show.blade.php as its source; no new module string or UI branch
 * needed. For an English-speaking target market this rewrites currency,
 * payment-method references, and cultural examples for that audience
 * without changing language; for a non-English target market (see
 * config/localization.php `language`) it's a genuine translation, adapted
 * the same way as part of the same pass — never a literal word-for-word
 * substitution either way.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows. Deliberately keeps its
 * own credit cost (config('credits.costs.localization')) rather than the
 * source module's cost, since adapting existing content is cheaper than
 * generating it from scratch.
 */
class LocalizationService
{
    protected const COST_KEY = 'localization';

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Generation $source, string $market): Generation
    {
        if (! array_key_exists($market, config('localization.markets'))) {
            throw new InvalidArgumentException("Unknown target market: {$market}.");
        }

        if (! in_array($source->module, config('localization.localizable_modules'), true)) {
            throw new InvalidArgumentException('This type of content can\'t be localized.');
        }

        if ($source->status !== 'completed') {
            throw new InvalidArgumentException('Only completed content can be localized.');
        }

        $cost = (int) config('credits.costs.'.self::COST_KEY);

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to localize content.");
        }

        $generation = $source->offer->generations()->create([
            'user_id' => $user->id,
            'module' => $source->module,
            'input' => $source->input,
            'target_market' => $market,
            'localized_from_id' => $source->id,
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunLocalizationGeneration::dispatch($generation);

        return $generation;
    }

    /**
     * Called by RunLocalizationGeneration inside the queue worker.
     * Always leaves the generation in a terminal state and never throws.
     */
    public function localize(Generation $generation): void
    {
        $source = $generation->localizedFrom;
        $market = config('localization.markets.'.$generation->target_market);

        if (! $source || ! $market) {
            $generation->update(['status' => 'failed', 'error_message' => 'Localization source or target market is missing.']);

            return;
        }

        $languageInstruction = $market['language'] === 'en'
            ? 'Keep the content in English, but rewrite it for this audience — this must not be just a light word-swap.'
            : "Translate the content fully into {$market['language_name']}, adapting it for this audience as part of the same translation pass — not a literal word-for-word translation.";

        $system = <<<'PROMPT'
            You are localizing existing affiliate marketing content for a different
            market. You will be given ORIGINAL_CONTENT as a JSON object. Return a JSON
            object with EXACTLY the same keys and structure as ORIGINAL_CONTENT — do
            not add, remove, or rename any key, and keep any list the same length.

            Rewrite currency amounts, payment-method references, and cultural examples
            so they genuinely fit the target market described below — a meaningful
            adaptation, not a superficial find-and-replace.

            The exact placeholder text "{{AFFILIATE_LINK}}" must be preserved EXACTLY
            as written, unchanged, wherever it appears in ORIGINAL_CONTENT — never
            translate, move, or alter it.
            PROMPT;

        $system .= "\n\n".$languageInstruction;

        $userPrompt = "Target market: {$market['label']} ({$market['currency']})\n"
            ."Market context: {$market['context']}\n\n"
            .'ORIGINAL_CONTENT (JSON):'."\n"
            .json_encode($source->output_meta, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $this->run($generation, $system, $userPrompt);
    }

    protected function run(Generation $generation, string $system, string $userPrompt): void
    {
        $user = $generation->user;
        $cost = (int) config('credits.costs.'.self::COST_KEY);

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

        $this->credits->spend($user, $cost, self::COST_KEY, $generation);
    }
}
