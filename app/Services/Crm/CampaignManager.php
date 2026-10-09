<?php

namespace App\Services\Crm;

use App\Jobs\SendCampaignBatch;
use App\Models\CrmCampaign;
use App\Models\CrmCampaignRecipient;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\Offer;
use App\Models\User;
use App\Notifications\CrmCampaignPaused;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * The lifecycle of a CRM campaign: AI drafting (charged per config('crm.
 * credits')), "Approve and Send", sending to contacts added later (by hand
 * or autopilot), pause/resume, and the paced sending loop itself — which
 * respects the daily cap, skips unsubscribed / email-less / already-sent
 * contacts, sends due follow-ups and stops them for anyone who replied,
 * and pauses the campaign if the email connection stops working.
 */
class CampaignManager
{
    public function __construct(
        protected CampaignAiWriter $writer,
        protected CampaignSender $sender,
        protected SendingQuota $quota,
        protected CreditManager $credits,
    ) {}

    /**
     * Drafts a campaign for $list with AI and charges for it.
     *
     * @param  array{name?: string|null, goal?: string|null, tone?: string|null, follow_ups?: int, personalize_opening?: bool}  $options
     *
     * @throws InsufficientCreditsException|AIGenerationException|RuntimeException
     */
    public function generate(User $user, CrmList $list, ?Offer $offer, array $options = []): CrmCampaign
    {
        $cost = (int) config('crm.credits.campaign_generate');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Writing a campaign costs {$cost} credits.");
        }

        try {
            $draft = $this->writer->writeCampaign($list, $offer, $options);
        } catch (AIGenerationException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException('The AI writer is unavailable right now — please try again in a minute.', previous: $e);
        }

        if ($draft['body'] === '') {
            throw new RuntimeException('The AI returned an empty email — please try again.');
        }

        return DB::transaction(function () use ($user, $list, $offer, $options, $draft, $cost) {
            $campaign = CrmCampaign::create([
                'user_id' => $user->id,
                'crm_list_id' => $list->id,
                'offer_id' => $offer?->id,
                'name' => ($options['name'] ?? null) ?: ($offer?->product_name ?? $list->name).' outreach — '.now()->format('M j'),
                'subject' => $draft['subject'],
                'body' => $draft['body'],
                'follow_up_steps' => $draft['follow_ups'],
                'tone' => $options['tone'] ?? null,
                'personalize_opening' => (bool) ($options['personalize_opening'] ?? false),
                'status' => 'draft',
                'generated_at' => now(),
                'credits_spent' => $cost,
            ]);

            $this->credits->spend($user, $cost, 'crm_campaign', $campaign);

            return $campaign;
        });
    }

    /**
     * "Approve and Send": queues every current list member and starts sending.
     *
     * @return int how many contacts were queued
     */
    public function approve(CrmCampaign $campaign): int
    {
        if (! $campaign->isDraft()) {
            throw new RuntimeException('This campaign has already been approved.');
        }

        if (trim($campaign->subject) === '' || trim((string) $campaign->body) === '') {
            throw new RuntimeException('Add a subject and an email body before approving.');
        }

        $campaign->update(['status' => 'sending', 'approved_at' => now(), 'paused_reason' => null]);

        $queued = $this->queueContacts($campaign, $campaign->list->contacts()->pluck('crm_contacts.id')->all(), 'launch');
        $this->kick($campaign);

        return $queued;
    }

    /**
     * Sends the campaign to list members added after it launched.
     */
    public function sendToNewContacts(CrmCampaign $campaign): int
    {
        if (! $campaign->isLaunched()) {
            throw new RuntimeException('Approve the campaign first.');
        }

        $ids = $campaign->newContactsQuery()->pluck('crm_contacts.id')->all();
        $queued = $this->queueContacts($campaign, $ids, 'new_contacts');

        if ($queued > 0 && $campaign->status === 'sent') {
            $campaign->update(['status' => 'sending']);
        }

        $this->kick($campaign);

        return $queued;
    }

    /**
     * Autopilot: a contact just joined the list. Idempotent.
     */
    public function queueForAutopilot(CrmCampaign $campaign, CrmContact $contact): bool
    {
        if (! $campaign->autopilot || ! $campaign->isLaunched()) {
            return false;
        }

        $queued = $this->queueContacts($campaign, [$contact->id], 'autopilot') > 0;

        if ($queued) {
            if ($campaign->status === 'sent') {
                $campaign->update(['status' => 'sending']);
            }

            $this->kick($campaign);
        }

        return $queued;
    }

    public function pause(CrmCampaign $campaign, ?string $reason = null): void
    {
        $campaign->update(['status' => 'paused', 'paused_reason' => $reason]);
    }

    public function resume(CrmCampaign $campaign): void
    {
        $campaign->update(['status' => 'sending', 'paused_reason' => null]);
        $this->kick($campaign);
    }

    /**
     * Inserts recipient rows (unique per campaign+contact, so re-queuing is
     * a no-op) and returns how many were new.
     *
     * @param  array<int, int>  $contactIds
     */
    public function queueContacts(CrmCampaign $campaign, array $contactIds, string $source): int
    {
        $queued = 0;

        foreach (array_chunk($contactIds, 500) as $chunk) {
            // Contacts that can never be emailed are recorded as skipped (or
            // unsubscribed) straight away, so the counts shown after
            // approving match the people who will actually get the email.
            $contacts = CrmContact::whereIn('id', $chunk)->get(['id', 'email', 'unsubscribed_at'])->keyBy('id');

            $rows = array_map(function (int $id) use ($campaign, $source, $contacts): array {
                $contact = $contacts->get($id);
                [$status, $error] = match (true) {
                    $contact === null => ['skipped', 'Contact was deleted.'],
                    $contact->unsubscribed_at !== null => ['unsubscribed', 'Unsubscribed — never emailed again.'],
                    blank($contact->email) || ! filter_var($contact->email, FILTER_VALIDATE_EMAIL) => ['skipped', 'No valid email address.'],
                    default => ['queued', null],
                };

                return [
                    'crm_campaign_id' => $campaign->id,
                    'crm_contact_id' => $id,
                    'user_id' => $campaign->user_id,
                    'status' => $status,
                    'error_message' => $error,
                    'source' => $source,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }, $chunk);

            CrmCampaignRecipient::insertOrIgnore($rows);
            $queued += CrmCampaignRecipient::where('crm_campaign_id', $campaign->id)
                ->whereIn('crm_contact_id', $chunk)
                ->where('status', 'queued')
                ->where('source', $source)
                ->count();
        }

        return $queued;
    }

    protected function kick(CrmCampaign $campaign): void
    {
        if (! $campaign->isPaused()) {
            SendCampaignBatch::dispatch($campaign->id);
        }
    }

    /**
     * Sends what's due for this campaign until the batch size, the daily
     * cap or the time budget runs out. Safe to call concurrently (locked
     * per campaign) and repeatedly (every send is idempotent per step).
     *
     * @return array{sent: int, failed: int, skipped: int, more: bool, reason: string|null}
     */
    public function process(CrmCampaign $campaign, ?float $deadline = null): array
    {
        $result = ['sent' => 0, 'failed' => 0, 'skipped' => 0, 'more' => false, 'reason' => null];
        $lock = Cache::lock('crm-campaign-send-'.$campaign->id, 120);

        if (! $lock->get()) {
            return [...$result, 'reason' => 'locked'];
        }

        try {
            return $this->processLocked($campaign->fresh(), $deadline ?? microtime(true) + (int) config('crm.sending.time_budget', 40), $result);
        } finally {
            $lock->release();
        }
    }

    /**
     * @param  array{sent: int, failed: int, skipped: int, more: bool, reason: string|null}  $result
     * @return array{sent: int, failed: int, skipped: int, more: bool, reason: string|null}
     */
    protected function processLocked(CrmCampaign $campaign, float $deadline, array $result): array
    {
        if (! in_array($campaign->status, ['sending', 'sent', 'approved'], true)) {
            return [...$result, 'reason' => 'not_active'];
        }

        $user = $campaign->user;
        $connection = $user->emailConnection;

        if (config('crm.require_connection') && (! $connection || ! $connection->isVerified())) {
            $this->pauseForConnection($campaign);

            return [...$result, 'reason' => 'connection'];
        }

        $batch = max(1, (int) config('crm.sending.batch_size', 25));
        $pauseMs = (int) config('crm.sending.pause_ms', 0);
        $followUps = $campaign->followUps();
        $consecutiveFailures = 0;

        foreach ($this->dueRecipients($campaign, $batch, count($followUps)) as $recipient) {
            if (microtime(true) >= $deadline) {
                return [...$result, 'more' => true, 'reason' => 'time'];
            }

            if ($this->quota->remaining($user) <= 0) {
                return [...$result, 'more' => true, 'reason' => 'daily_cap'];
            }

            $contact = $recipient->contact;
            $skip = match (true) {
                $contact === null => ['skipped', 'Contact was deleted.'],
                $contact->isUnsubscribed() => ['unsubscribed', 'Unsubscribed — never emailed again.'],
                $contact->replied_at !== null && $recipient->steps_sent > 0 => ['replied', null],
                blank($contact->email) || ! filter_var($contact->email, FILTER_VALIDATE_EMAIL) => ['skipped', 'No valid email address.'],
                default => null,
            };

            if ($skip) {
                $recipient->update(['status' => $skip[0], 'error_message' => $skip[1], 'next_step_due_at' => null]);
                $result['skipped']++;

                continue;
            }

            $step = $recipient->steps_sent;
            $send = $this->sender->send($recipient, $step);

            if ($send->status === 'sent') {
                $consecutiveFailures = 0;
                $result['sent']++;
                $nextDelay = $followUps[$step]['delay_days'] ?? null;
                $recipient->update([
                    'status' => 'sent',
                    'steps_sent' => $step + 1,
                    'last_sent_at' => now(),
                    'next_step_due_at' => $nextDelay ? now()->addDays($nextDelay) : null,
                    'error_message' => null,
                ]);

                if ($contact->status === 'new') {
                    $contact->update(['status' => 'contacted']);
                }

                $campaign->update(['last_sent_at' => now()]);
            } else {
                $result['failed']++;
                $consecutiveFailures++;
                $recipient->update(['status' => $step === 0 ? 'failed' : 'sent', 'next_step_due_at' => null, 'error_message' => $send->error_message]);

                if ($this->looksLikeConnectionFailure((string) $send->error_message) || $consecutiveFailures >= 3) {
                    $connection?->update([
                        'verification_status' => 'failed',
                        'verification_message' => app(ConnectionErrorExplainer::class)->explain((string) $send->error_message, $connection->isGmail() ? 'gmail' : 'smtp', (string) $connection->credential('host')),
                        'last_checked_at' => now(),
                    ]);
                    $this->pauseForConnection($campaign);

                    return [...$result, 'reason' => 'connection'];
                }
            }

            if ($pauseMs > 0) {
                usleep($pauseMs * 1000);
            }
        }

        $stillQueued = $campaign->recipients()->where('status', 'queued')->exists();
        $hasMore = $stillQueued || $this->dueRecipients($campaign, 1, count($followUps))->isNotEmpty();

        if (! $stillQueued && $campaign->status !== 'sent') {
            $campaign->update(['status' => 'sent']);
        }

        return [...$result, 'more' => $hasMore];
    }

    /**
     * Queued recipients first, then follow-ups that have come due for
     * contacts who haven't replied.
     *
     * @return Collection<int, CrmCampaignRecipient>
     */
    protected function dueRecipients(CrmCampaign $campaign, int $limit, int $followUpCount): Collection
    {
        $queued = $campaign->recipients()->with('contact')->where('status', 'queued')->orderBy('id')->limit($limit)->get();

        if ($queued->count() >= $limit || $followUpCount === 0) {
            return $queued;
        }

        $due = $campaign->recipients()->with('contact')
            ->where('status', 'sent')
            ->whereNull('replied_at')
            ->whereNotNull('next_step_due_at')
            ->where('next_step_due_at', '<=', now())
            ->where('steps_sent', '<=', $followUpCount)
            ->orderBy('next_step_due_at')
            ->limit($limit - $queued->count())
            ->get();

        return $queued->concat($due);
    }

    /**
     * Pauses a running campaign because its sender broke, and tells the
     * user once.
     */
    public function pauseForConnection(CrmCampaign $campaign): void
    {
        if ($campaign->isPaused()) {
            return;
        }

        $this->pause($campaign, 'connection');
        $campaign->user->notify(new CrmCampaignPaused($campaign));
    }

    protected function looksLikeConnectionFailure(string $error): bool
    {
        $error = strtolower($error);

        foreach (['authenticat', 'username and password', 'invalid credentials', '535', 'reconnect gmail', 'revoked', 'invalid_grant', 'unauthorized'] as $needle) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }

        return false;
    }
}
