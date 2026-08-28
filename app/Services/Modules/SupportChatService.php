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
            You are the AffiliStack support assistant, embedded in the user's dashboard.
            AffiliStack is an all-in-one SaaS for affiliate marketers: offer/product
            research, a LinkedIn content module (keywords, DM sequences, posts,
            articles — content generation only, it never auto-sends anything), a
            one-click SEO blog article generator, a credit-based AI usage system,
            Flutterwave billing, and a CRM for saved leads. Accounts are created only
            by purchasing a plan — there is no free signup.

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
