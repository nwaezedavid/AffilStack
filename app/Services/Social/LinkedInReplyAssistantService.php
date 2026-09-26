<?php

namespace App\Services\Social;

use App\Models\LinkedinReplyDraft;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Support\Facades\DB;

/**
 * Task #3: "paste their reply, get an AI-drafted response" — the DM
 * sequence module (LinkedInService::dmSequence) only ever writes the FIRST
 * outreach and scripted follow-ups; this is for the live back-and-forth
 * once a real prospect actually responds. Synchronous (not queued) — the
 * user is mid-conversation and wants a draft to copy right now, the same
 * pattern as PaymentCredentialAdvisor's inline AI explanation.
 */
class LinkedInReplyAssistantService
{
    public function __construct(protected AIProvider $ai, protected CreditManager $credits) {}

    /**
     * @throws InsufficientCreditsException
     * @throws AIGenerationException
     */
    public function draft(User $user, ?Offer $offer, string $theirMessage): LinkedinReplyDraft
    {
        $cost = (int) config('credits.costs.linkedin_reply_draft');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to draft a LinkedIn reply.");
        }

        $system = <<<'PROMPT'
            You are an elite LinkedIn sales conversationalist. You write replies to
            real prospect messages that feel personal and confident, never
            salesy or scripted, and that move the conversation toward a sale
            while building the prospect's trust and confidence in the product
            and the person messaging them.

            Rules for the reply you draft:
            - Sound like a real person replying on LinkedIn, not a marketing email.
            - Directly acknowledge what they actually said before pivoting.
            - Handle objections or hesitation with genuine reassurance and social
              proof style confidence-building, never pressure or urgency tactics.
            - End with one clear, low-friction next step (a question, or a small
              ask) — never more than one call to action.
            - Under 700 characters. No hashtags, no emojis unless the prospect
              used them first.

            Return a JSON object with exactly this key:
            - "draft_reply": the ready-to-send message text.
            PROMPT;

        $context = $offer
            ? "Product being discussed: {$offer->product_name}\nIdeal customer: {$offer->ideal_customer_summary}\nAngle: {$offer->recommended_angle}\n\nTheir message:\n{$theirMessage}"
            : "Their message:\n{$theirMessage}";

        // Let AIGenerationException propagate as-is — the controller
        // distinguishes it from InsufficientCreditsException to show the
        // right message (no credit was spent either way).
        $result = $this->ai->generateJson($system, $context);

        // Saved and charged atomically — a draft whose charge fails (credits
        // spent by a parallel request meanwhile) is rolled back, not kept free.
        return DB::transaction(function () use ($user, $offer, $theirMessage, $result, $cost) {
            $draft = LinkedinReplyDraft::create([
                'user_id' => $user->id,
                'offer_id' => $offer?->id,
                'their_message' => $theirMessage,
                'draft_reply' => (string) ($result['draft_reply'] ?? ''),
            ]);

            $this->credits->spend($user, $cost, 'linkedin_reply_draft', $draft);

            return $draft;
        });
    }
}
