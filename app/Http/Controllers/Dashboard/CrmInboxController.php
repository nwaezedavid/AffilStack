<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmReply;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Crm\Inbox\InboxReplier;
use App\Services\Crm\Inbox\ReplyPollingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/**
 * Replies from CRM contacts, one conversation per contact (unread first),
 * with the whole thread, "Draft reply with AI" and a threaded Send on the
 * user's own connection.
 */
class CrmInboxController extends Controller
{
    public function __construct(protected InboxReplier $replier) {}

    public function index(): View
    {
        $user = auth()->user();

        $latestIds = CrmReply::where('user_id', $user->id)
            ->selectRaw('max(id) as id')
            ->groupBy('crm_contact_id')
            ->pluck('id');

        $conversations = CrmReply::whereIn('id', $latestIds)
            ->with(['contact.lists', 'campaign:id,name,offer_id', 'campaign.offer:id,product_name'])
            ->get()
            ->map(function (CrmReply $reply) use ($user) {
                $reply->setAttribute('unread_count', CrmReply::where('user_id', $user->id)->where('crm_contact_id', $reply->crm_contact_id)->whereNull('read_at')->count());

                return $reply;
            })
            ->sortBy([fn ($a, $b) => ($b->unread_count > 0) <=> ($a->unread_count > 0), fn ($a, $b) => $b->received_at <=> $a->received_at])
            ->values();

        return view('dashboard.crm.inbox.index', [
            'conversations' => $conversations,
            'connection' => $user->emailConnection,
        ]);
    }

    public function show(CrmContact $contact, CreditManager $credits): View
    {
        abort_unless($contact->user_id === auth()->id(), 403);

        $latest = $contact->replies()->latest('received_at')->latest('id')->first();
        abort_unless($latest !== null, 404);

        $contact->replies()->whereNull('read_at')->update(['read_at' => now()]);

        $subject = (string) ($latest->subject ?: $latest->send?->subject ?: 'our conversation');

        return view('dashboard.crm.inbox.show', [
            'contact' => $contact->load('lists'),
            'latest' => $latest,
            'thread' => $this->replier->thread($contact),
            'offer' => $this->replier->offerFor($latest),
            'campaign' => $latest->campaign,
            'replySubject' => str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject,
            'draft' => session('ai_draft'),
            'cost' => (int) config('crm.credits.reply_draft'),
            'balance' => $credits->balance(auth()->user()),
            'connection' => auth()->user()->emailConnection,
        ]);
    }

    public function draft(Request $request, CrmReply $reply): RedirectResponse
    {
        abort_unless($reply->user_id === auth()->id(), 403);
        $validated = $request->validate(['instructions' => 'nullable|string|max:500']);

        try {
            $draft = $this->replier->draft(auth()->user(), $reply, $validated['instructions'] ?? null);
        } catch (InsufficientCreditsException $e) {
            return back()->with('error', $e->getMessage().' Top up your credits or write the reply yourself.');
        } catch (AIGenerationException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ai_draft', $draft)->with('success', 'Draft ready — edit anything you like, then send.');
    }

    public function send(Request $request, CrmReply $reply): RedirectResponse
    {
        abort_unless($reply->user_id === auth()->id(), 403);

        $validated = $request->validate([
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
        ]);

        try {
            $this->replier->send(auth()->user(), $reply, $validated['subject'], $validated['body']);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('crm.inbox.show', $reply->crm_contact_id)->with('success', 'Reply sent from your own inbox, in the same thread.');
    }

    /**
     * "Check for replies now" — polls just this user's mailbox.
     */
    public function check(ReplyPollingService $polling): RedirectResponse
    {
        $connection = auth()->user()->emailConnection;

        if (! $connection || ! $connection->replyDetectionMethod()) {
            return redirect()->route('email-connections.index')->with('error', 'Set up reply detection (IMAP) on the Email Sending page first.');
        }

        $result = $polling->pollOne($connection);

        return back()->with(
            $result === null ? 'error' : 'success',
            $result === null ? 'We couldn\'t check your inbox just now — see the status on the Email Sending page.' : ($result['replies'] > 0 ? "{$result['replies']} new repl(ies) found." : 'No new replies yet — we also check automatically every 5 minutes.'),
        );
    }
}
