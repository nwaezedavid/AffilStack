<?php

namespace App\Services\Modules;

use App\Models\AiChatMessage;
use App\Models\FaqItem;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;

/**
 * The AI live support widget. Answers from the admin-managed FAQ/knowledge
 * base first, and tells the caller when it thinks a human is needed so the
 * dashboard can offer to open a ticket with the transcript attached. Free to
 * use — this is a retention/support feature, not a monetized credit module,
 * and the real per-message AI cost is negligible either way.
 */
class SupportChatService
{
    protected const HISTORY_LIMIT = 10;

    public function __construct(protected AIProvider $ai) {}

    /**
     * @return array{reply: string, should_escalate: bool}
     */
    public function reply(User $user, string $message): array
    {
        $history = AiChatMessage::where('user_id', $user->id)
            ->latest()
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->reverse();

        AiChatMessage::create(['user_id' => $user->id, 'role' => 'user', 'content' => $message]);

        $system = $this->systemPrompt();
        $conversation = $history->map(fn ($m) => strtoupper($m->role).': '.$m->content)->implode("\n");
        $userPrompt = ($conversation ? "Conversation so far:\n{$conversation}\n\n" : '')."New message from {$user->name}: {$message}";

        try {
            $result = $this->ai->generateJson($system, $userPrompt);
        } catch (AIGenerationException) {
            return [
                'reply' => "Sorry, I'm having trouble responding right now. Want me to open a support ticket instead?",
                'should_escalate' => true,
            ];
        }

        $reply = (string) ($result['reply'] ?? "Sorry, I didn't catch that — could you rephrase?");
        $shouldEscalate = (bool) ($result['should_escalate'] ?? false);

        AiChatMessage::create(['user_id' => $user->id, 'role' => 'assistant', 'content' => $reply]);

        return ['reply' => $reply, 'should_escalate' => $shouldEscalate];
    }

    /**
     * The last N exchanges, formatted for pasting into a new support ticket
     * when the user (or the AI) decides this needs a human.
     */
    public function recentTranscript(User $user): string
    {
        return AiChatMessage::where('user_id', $user->id)
            ->latest()
            ->limit(self::HISTORY_LIMIT * 2)
            ->get()
            ->reverse()
            ->map(fn ($m) => strtoupper($m->role).': '.$m->content)
            ->implode("\n\n");
    }

    protected function systemPrompt(): string
    {
        $faq = FaqItem::where('is_published', true)
            ->orderBy('sort_order')
            ->get()
            ->map(fn ($f) => "Q: {$f->question}\nA: {$f->answer}")
            ->implode("\n\n");

        return <<<PROMPT
            You are the AffilStack support assistant, embedded in the user's dashboard.

            AffilStack is an all-in-one SaaS for affiliate marketers and content
            businesses. Its main modules:
            - Offer/product research: find and evaluate affiliate offers and products.
            - Google Maps lead-finding: search local businesses as sales leads.
            - A CRM for saved leads, with pipeline stages.
            - A LinkedIn content module: keyword research, DM sequences, posts, and
              articles — this only generates content, it never auto-sends or auto-posts
              anything on the user's behalf.
            - A one-click SEO blog article generator.
            - A credit-based AI usage system: every plan includes a monthly credit
              allowance for AI features, and users can buy extra credit top-up packs
              (Quick Top-Up, Power Pack, Bulk Pack) if they run out mid-cycle.
            - Billing via Flutterwave and Stripe (and other supported gateways
              depending on the user's region), subscription plans billed monthly or
              yearly.
            - An affiliate/referral program: anyone can apply to become an AffilStack
              affiliate and earn commission referring new customers, tracked through
              their own "Partner Portal" dashboard.
            - A public Learning Centre with tutorial videos, and admin-published
              help articles/pages (Terms, Privacy Policy, Refund & Cancellation
              Policy, Cookie Policy, About, etc.).
            - "Continue with Google" sign-in, in addition to email/password.

            Accounts are created only by purchasing a plan — there is no free signup.
            If asked about a topic outside AffilStack itself, politely redirect to
            what AffilStack does.

            Answer from the knowledge base below whenever it covers the question.
            Be concise, warm, and specific. If you don't know the answer, or the
            question is about the user's own billing/account/data in a way you can't
            verify, or the user explicitly asks for a human, say so plainly and set
            should_escalate to true rather than guessing.

            Knowledge base:
            {$faq}

            Respond with ONLY a JSON object with exactly these keys:
            - "reply": your response to the user, plain text, under 120 words.
            - "should_escalate": true if this needs a human support agent, false otherwise.
            PROMPT;
    }
}
