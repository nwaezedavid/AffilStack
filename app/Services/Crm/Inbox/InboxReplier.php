<?php

namespace App\Services\Crm\Inbox;

use App\Models\CrmContact;
use App\Models\CrmEmailSend;
use App\Models\CrmReply;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Crm\CampaignAiWriter;
use App\Services\Crm\CampaignEmailRenderer;
use App\Services\Crm\CampaignSender;
use App\Services\Crm\PersonalEmailSender;
use App\Services\Crm\SendingQuota;
use App\Services\Links\LinkCloakingService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The Inbox's "Draft reply with AI" and "Send": drafts a sales-minded
 * answer from the product research, the original campaign email and the
 * whole thread (charged per config('crm.credits.reply_draft')), and sends
 * the user's edited reply on their own connection, threaded under the
 * contact's message (In-Reply-To/References, Gmail threadId) with the
 * affiliate link cloaked and click-tracked.
 */
class InboxReplier
{
    public function __construct(
        protected CampaignAiWriter $writer,
        protected CreditManager $credits,
        protected PersonalEmailSender $sender,
        protected CampaignEmailRenderer $renderer,
        protected LinkCloakingService $linkCloaking,
        protected SendingQuota $quota,
    ) {}

    /**
     * The contact's whole conversation, oldest first: our sends and their replies.
     *
     * @return Collection<int, array{direction: string, at: Carbon|null, subject: string|null, body: string, model: CrmEmailSend|CrmReply}>
     */
    public function thread(CrmContact $contact): Collection
    {
        $out = $contact->emailSends()->where('status', 'sent')->get()->map(fn (CrmEmailSend $send) => [
            'direction' => 'out',
            'at' => $send->sent_at ?? $send->created_at,
            'subject' => $send->subject,
            'body' => $send->body,
            'model' => $send,
        ]);

        $in = $contact->replies()->get()->map(fn (CrmReply $reply) => [
            'direction' => 'in',
            'at' => $reply->received_at ?? $reply->created_at,
            'subject' => $reply->subject,
            'body' => (string) ($reply->body_clean ?: $reply->body_text),
            'model' => $reply,
        ]);

        return $out->concat($in)->sortBy(fn (array $item) => $item['at']?->getTimestamp() ?? 0)->values();
    }

    /**
     * The offer this conversation is about: the campaign's, else the
     * contact's product list's.
     */
    public function offerFor(CrmReply $reply): ?Offer
    {
        return $reply->campaign?->offer
            ?? $reply->contact?->lists()->whereNotNull('offer_id')->first()?->offer;
    }

    /**
     * @throws InsufficientCreditsException|AIGenerationException|RuntimeException
     */
    public function draft(User $user, CrmReply $reply, ?string $instructions = null): string
    {
        $cost = (int) config('crm.credits.reply_draft');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Drafting a reply costs {$cost} credits.");
        }

        $transcript = $this->thread($reply->contact)
            ->take(-8)
            ->map(fn (array $item) => ($item['direction'] === 'out' ? 'ME' : 'THEM').' ('.($item['at']?->format('M j') ?? '').'): '.Str::limit($item['body'], 1200))
            ->implode("\n\n");

        try {
            $draft = $this->writer->draftReply($reply, $transcript, $this->offerFor($reply), $instructions);
        } catch (AIGenerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('The AI writer is unavailable right now — please try again in a minute.', previous: $e);
        }

        if ($draft === '') {
            throw new RuntimeException('The AI returned an empty reply — please try again.');
        }

        $this->credits->spend($user, $cost, 'crm_reply_draft', $reply);

        // Shown to the user with a readable placeholder they can keep or delete.
        return str_replace(Offer::LINK_PLACEHOLDER, '{affiliate_link}', $draft);
    }

    /**
     * Sends $body as a threaded reply to $reply on the user's own connection.
     *
     * @throws RuntimeException
     */
    public function send(User $user, CrmReply $reply, string $subject, string $body): CrmEmailSend
    {
        $connection = $user->emailConnection;
        $contact = $reply->contact;

        if (! $connection || ! $connection->isVerified()) {
            throw new RuntimeException('Connect and verify your email on the Email Sending page before replying.');
        }

        if ($contact->isUnsubscribed()) {
            throw new RuntimeException('This contact unsubscribed — they can\'t be emailed again.');
        }

        if ($this->quota->remaining($user) <= 0) {
            throw new RuntimeException('You\'ve hit today\'s sending limit. Replies can go out again tomorrow.');
        }

        $offer = $this->offerFor($reply);
        $text = str_replace('{affiliate_link}', $offer ? Offer::LINK_PLACEHOLDER : '', $body);
        $shortLink = $offer && $offer->hasLinkPlaceholder($text) ? $this->linkCloaking->getOrCreateForOffer($offer, CampaignSender::LINK_MODULE) : null;
        $cloaked = $offer ? $offer->cloak($text, CampaignSender::LINK_MODULE) : $text;
        $token = Str::random(32);
        $final = $shortLink ? str_replace($shortLink->short_url, route('crm.track.click', $token), $cloaked) : $cloaked;

        $references = array_values(array_unique(array_filter([
            ...preg_split('/\s+/', (string) $reply->references) ?: [],
            $reply->message_id,
        ])));
        $messageId = CampaignSender::newMessageId($connection->connected_email);

        $send = CrmEmailSend::create([
            'user_id' => $user->id,
            'crm_contact_id' => $contact->id,
            'kind' => 'reply',
            'crm_campaign_id' => $reply->crm_campaign_id,
            'crm_campaign_recipient_id' => $reply->crm_campaign_recipient_id,
            'subject' => Str::limit($subject, 250, ''),
            'body' => $final,
            'link_destination' => $shortLink?->short_url,
            'tracking_token' => $token,
            'message_id' => $messageId,
            'in_reply_to' => $reply->message_id,
            'status' => 'queued',
        ]);

        $quoted = ($reply->received_at?->format('D, M j, Y \a\t g:i A') ?? 'Earlier').', '.($reply->from_name ?: $reply->from_email)." wrote:\n".Str::limit((string) ($reply->body_clean ?: $reply->body_text), 2000);

        try {
            $ids = $this->sender->send($connection, (string) $contact->email, $send->subject, $this->renderer->render($user, $contact, $final, [
                'quoted' => $quoted,
                'tracking_token' => $token,
            ]), [
                'message_id' => $messageId,
                'in_reply_to' => $reply->message_id,
                'references' => $references,
                'thread_id' => $reply->send?->provider_thread_id,
                'text_body' => $final."\n\n".preg_replace('/^/m', '> ', $quoted),
            ]);

            $send->update([
                'status' => 'sent',
                'sent_at' => now(),
                'provider_message_id' => $ids['provider_message_id'] ?? null,
                'provider_thread_id' => $ids['provider_thread_id'] ?? null,
            ]);
        } catch (Throwable $e) {
            report($e);
            $send->update(['status' => 'failed', 'error_message' => Str::limit($e->getMessage(), 1000)]);

            throw new RuntimeException('Your reply could not be sent — check your connection on the Email Sending page.', previous: $e);
        }

        $reply->contact->replies()->whereNull('read_at')->update(['read_at' => now()]);

        return $send;
    }
}
