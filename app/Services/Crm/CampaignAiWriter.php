<?php

namespace App\Services\Crm;

use App\Models\CrmCampaign;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\CrmReply;
use App\Models\Offer;
use App\Services\AI\AIProvider;

/**
 * The AI copywriting behind CRM campaigns and the Inbox: a campaign's
 * cold email (+ optional follow-ups) written once per list from the
 * product research, an optional one-line personal opener per contact at
 * send time, and a sales-minded reply to a contact's response. Prompt style
 * follows EmailNurtureService: consultative, specific, never spammy.
 * Charging credits is the caller's job (CampaignManager / InboxReplier).
 */
class CampaignAiWriter
{
    public function __construct(protected AIProvider $ai) {}

    /**
     * @param  array{goal?: string|null, tone?: string|null, follow_ups?: int}  $options
     * @return array{subject: string, body: string, follow_ups: array<int, array{delay_days: int, subject: string, body: string}>}
     */
    public function writeCampaign(CrmList $list, ?Offer $offer, array $options = []): array
    {
        $followUps = max(0, min(3, (int) ($options['follow_ups'] ?? 2)));
        $tone = $options['tone'] ?: 'friendly and professional';

        $system = <<<PROMPT
            You are a top-performing B2B cold email copywriter who writes short,
            personal, consultative outreach that gets replies — never spammy,
            never hype, no ALL CAPS, no exclamation-mark overload, nothing that
            trips spam filters ("free!!!", "guaranteed", "act now").

            Write ONE cold email template that will be sent to many contacts, so
            use these merge fields exactly as written (single curly braces) and
            nothing else in braces:
            {first_name}, {company}, {city}, {website}, {product_name}, {affiliate_link}

            Rules:
            - Greet with "Hi {first_name}," on the first line.
            - 70-130 words, plain text, short paragraphs, conversational.
            - Tone: {$tone}.
            - Open with something relevant to their business ({company} in {city}), then one
              specific pain point the product solves, then ONE clear low-friction call to
              action. Include {affiliate_link} exactly once, where it reads naturally.
            - Sign off simply (e.g. "Best,") with NO name — the signature is added later.
            - Subject: under 55 characters, specific, lower-pressure, may use {company} or {first_name}; never "Following up".

            Then write exactly {$followUps} follow-up email(s) for contacts who haven't replied —
            each 40-80 words, adding NEW value (a quick tip, a result, a question),
            never "just checking in". The last follow-up is a polite close-the-loop.

            Return JSON with exactly these keys:
            - "subject": string
            - "body": string (use \\n for line breaks)
            - "follow_ups": array of {$followUps} objects with "delay_days" (integer, 2-5), "subject" (string, may be empty to reply in the same thread) and "body" (string)
            PROMPT;

        $result = $this->ai->generateJson($system, $this->productContext($list, $offer, $options['goal'] ?? null));

        return [
            'subject' => trim((string) ($result['subject'] ?? '')),
            'body' => trim((string) ($result['body'] ?? '')),
            'follow_ups' => collect($result['follow_ups'] ?? [])
                ->filter(fn ($step) => is_array($step) && trim((string) ($step['body'] ?? '')) !== '')
                ->take($followUps)
                ->map(fn (array $step) => [
                    'delay_days' => max(1, min(14, (int) ($step['delay_days'] ?? 3))),
                    'subject' => trim((string) ($step['subject'] ?? '')),
                    'body' => trim((string) $step['body']),
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * One personal first sentence for this contact (no greeting).
     */
    public function openingLine(CrmCampaign $campaign, CrmContact $contact): string
    {
        $system = <<<'PROMPT'
            Write ONE short, natural opening sentence (max 25 words) for a cold
            email, referencing something specific and plausible about the
            recipient's business from the details given. No greeting, no
            flattery clichés, no questions about how they are, no emojis, no
            made-up facts (stay general if details are thin). Return only the sentence.
            PROMPT;

        $user = "Recipient business: {$contact->company}\n"
            ."Name: {$contact->name}\nTitle: {$contact->title}\n"
            ."Location: {$contact->location}\nWebsite: {$contact->website}\n"
            .'Business type: '.($contact->raw_data['type'] ?? 'unknown')."\n"
            .'We are emailing about: '.($campaign->offer?->product_name ?? $campaign->name);

        $line = trim($this->ai->generateText($system, $user), " \t\n\r\"");

        return mb_substr(preg_replace('/\s+/', ' ', $line) ?? $line, 0, 300);
    }

    /**
     * A reply aimed at moving the conversation toward the sale.
     */
    public function draftReply(CrmReply $reply, string $threadTranscript, ?Offer $offer, ?string $instructions = null): string
    {
        $system = <<<'PROMPT'
            You are an expert, consultative sales closer replying to a prospect
            who answered a cold email. Read the thread and write the NEXT reply.

            - Answer their actual question or objection directly and honestly
              (price, trust, timing, "not interested", "send more info", etc.).
            - Move one step toward the sale: propose a clear, easy next step
              (try it, a quick call, a specific feature to look at).
            - When it fits naturally, include the link placeholder {{AFFILIATE_LINK}}
              exactly once; never if they asked to stop or said no firmly — then
              reply graciously and stop selling.
            - 50-130 words, plain text, warm, human, no hype, no emojis.
            - Start with "Hi <their first name>," and end with a simple sign-off
              like "Best," and NO name (the signature is added later).
            Return only the email body.
            PROMPT;

        $context = $this->productContext(null, $offer, null)
            ."\n\nConversation so far (oldest first):\n".$threadTranscript
            ."\n\nTheir latest message:\n".($reply->body_clean ?: $reply->body_text);

        if ($instructions) {
            $context .= "\n\nExtra guidance from the sender: ".$instructions;
        }

        return trim($this->ai->generateText($system, $context));
    }

    protected function productContext(?CrmList $list, ?Offer $offer, ?string $goal): string
    {
        $lines = [];

        if ($offer) {
            $research = $offer->research_data ?? [];
            $lines[] = "Product: {$offer->product_name}";
            $lines[] = "Official URL: {$offer->product_url}";
            $lines[] = 'Ideal customer: '.($offer->ideal_customer_summary ?: 'small business owners');
            $lines[] = 'Angle to use: '.($offer->recommended_angle ?: 'save time and grow revenue');

            if (! empty($research['pain_points']) && is_array($research['pain_points'])) {
                $lines[] = 'Pain points it solves: '.implode('; ', array_slice($research['pain_points'], 0, 5));
            }
        }

        if ($list) {
            $lines[] = "Contact list: {$list->name}".($list->description ? " — {$list->description}" : '');
            $types = $list->contacts()->whereNotNull('raw_data')->limit(20)->get()
                ->map(fn (CrmContact $c) => $c->raw_data['type'] ?? null)->filter()->countBy()->sortDesc()->keys()->take(3);

            if ($types->isNotEmpty()) {
                $lines[] = 'Most contacts are: '.$types->implode(', ');
            }
        }

        if ($goal) {
            $lines[] = "Campaign goal from the sender: {$goal}";
        }

        return implode("\n", $lines);
    }
}
