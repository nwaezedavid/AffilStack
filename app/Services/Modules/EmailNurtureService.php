<?php

namespace App\Services\Modules;

use App\Jobs\RunEmailNurtureGeneration;
use App\Models\CrmContact;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;

/**
 * Feature 6 (Phase 3 backlog, item 6): email nurture generator — the bridge
 * between the CRM (item 7, still ahead — contacts are added manually until
 * then) and the copywriting engine every other module already uses. One
 * click drafts a 5-email sequence introducing a specific offer to a real,
 * named CRM contact, so the copy is personalized to them rather than a
 * generic "{{first_name}}" template — unlike the LinkedIn DM sequence,
 * which is written once and reused across many prospects, a nurture
 * sequence here is generated per (offer, contact) pair.
 *
 * Runs in the background — see OfferResearchService for the same
 * queue()/run() split every other module follows.
 */
class EmailNurtureService
{
    protected const MODULE = 'email_nurture';

    public function __construct(
        protected AIProvider $ai,
        protected CreditManager $credits,
    ) {}

    public function queue(User $user, Offer $offer, CrmContact $contact): Generation
    {
        $cost = (int) config('credits.costs.'.self::MODULE);

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run ".self::MODULE.'.');
        }

        $generation = $offer->generations()->create([
            'user_id' => $user->id,
            'module' => self::MODULE,
            'input' => ['offer_id' => $offer->id, 'contact_id' => $contact->id],
            'credits_spent' => 0,
            'status' => 'queued',
        ]);

        RunEmailNurtureGeneration::dispatch($generation);

        return $generation;
    }

    public function generate(Generation $generation): void
    {
        $system = <<<'PROMPT'
            You are a warm, consultative email copywriter who writes nurture
            sequences that build trust before ever pitching — never a hard sell,
            never generic "just checking in" filler.

            Return a JSON object with exactly this key:
            - "emails": array of exactly 5 objects, each with:
              - "step": integer 1-5
              - "send_timing": when to send relative to the previous step, e.g. "Day 0" or "Day 4 if no reply"
              - "subject": under 60 characters, specific and curiosity-driven, never "Following up"
              - "body": the full email body, 80-150 words, addressed to the contact by their first name, plain conversational tone (no heavy formatting), signed off simply.

            Email 1 introduces yourself and why you're reaching out — no pitch yet.
            Emails 2-3 build value: a relevant insight, a quick win, or a question
            about their situation. Emails 4-5 make the actual recommendation,
            naming the product and using the placeholder "{{AFFILIATE_LINK}}" as
            the link, with email 5 a low-pressure final note (not a hard close).
            PROMPT;

        $this->run($generation, $system, $this->context($generation));
    }

    /**
     * Called by RunEmailNurtureGeneration inside the queue worker. Always
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

    protected function context(Generation $generation): string
    {
        $offer = $generation->offer;
        $contact = CrmContact::find($generation->input['contact_id'] ?? null);

        $contactLines = $contact
            ? "Recipient name: {$contact->name}\n"
                ."Recipient company: {$contact->company}\n"
                ."Recipient title: {$contact->title}\n"
            : "Recipient: unknown — address them generically as \"there\".\n";

        return $contactLines
            ."Product: {$offer->product_name}\n"
            ."Official URL: {$offer->product_url}\n"
            ."Ideal customer: {$offer->ideal_customer_summary}\n"
            ."Angle to use: {$offer->recommended_angle}\n";
    }
}
