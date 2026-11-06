<?php

namespace App\Http\Controllers\Api\V1\Mobile;

use App\Http\Controllers\Controller;
use App\Models\CrmContact;
use App\Models\CrmReply;
use App\Services\Agents\Customer\ReplyCopilot;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Crm\Inbox\InboxReplier;
use App\Support\Mobile\SeatAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Inbox & replies in the phone app: conversations with contacts who wrote
 * back, the thread, Reply Copilot's suggested answer, an AI draft on
 * request, and sending from the customer's own mailbox — all through the
 * dashboard's InboxReplier and ReplyCopilot.
 */
class InboxController extends Controller
{
    public function __construct(protected InboxReplier $replier) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'crm.inbox');

        $latestIds = CrmReply::where('user_id', $user->id)->selectRaw('max(id) as id')->groupBy('crm_contact_id')->pluck('id');
        $unread = CrmReply::where('user_id', $user->id)->whereNull('read_at')->selectRaw('crm_contact_id, count(*) as total')->groupBy('crm_contact_id')->pluck('total', 'crm_contact_id');

        $conversations = CrmReply::whereIn('id', $latestIds)
            ->with('contact:id,name,email,company')
            ->get()
            ->map(fn (CrmReply $reply): array => [
                'contact_id' => $reply->crm_contact_id,
                'contact_name' => $reply->contact?->name,
                'contact_company' => $reply->contact?->company,
                'subject' => $reply->subject,
                'snippet' => mb_substr(trim(preg_replace('/\s+/', ' ', (string) ($reply->body_clean ?: $reply->body_text))), 0, 140),
                'received_at' => ($reply->received_at ?? $reply->created_at)?->toIso8601String(),
                'unread_count' => (int) ($unread[$reply->crm_contact_id] ?? 0),
                'has_suggestion' => in_array($reply->copilot_status, ['drafted', 'auto_queued'], true),
            ])
            ->sortBy([fn (array $a, array $b) => ($b['unread_count'] > 0) <=> ($a['unread_count'] > 0), fn (array $a, array $b) => strcmp((string) $b['received_at'], (string) $a['received_at'])])
            ->values();

        return response()->json([
            'data' => $conversations,
            'email_connected' => (bool) $user->emailConnection?->isVerified(),
        ]);
    }

    public function show(Request $request, CrmContact $contact, CreditManager $credits): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'crm.inbox.show');
        abort_unless($contact->user_id === $user->id, 404);

        $latest = $contact->replies()->latest('received_at')->latest('id')->first();
        abort_unless($latest !== null, 404);

        $contact->replies()->whereNull('read_at')->update(['read_at' => now()]);

        $subject = (string) ($latest->subject ?: $latest->send?->subject ?: 'our conversation');
        $hasSuggestion = in_array($latest->copilot_status, ['drafted', 'auto_queued'], true);

        return response()->json([
            'contact' => ['id' => $contact->id, 'name' => $contact->name, 'email' => $contact->email, 'company' => $contact->company],
            'reply_id' => $latest->id,
            'reply_subject' => str_starts_with(strtolower($subject), 're:') ? $subject : 'Re: '.$subject,
            'messages' => $this->replier->thread($contact)->map(fn (array $item): array => [
                'direction' => $item['direction'],
                'at' => $item['at']?->toIso8601String(),
                'subject' => $item['subject'],
                'body' => trim((string) $item['body']),
            ])->values(),
            'suggestion' => $hasSuggestion ? [
                'body' => app(ReplyCopilot::class)->draftForEditing($latest),
                'auto_send_at' => $latest->copilot_status === 'auto_queued' ? $latest->copilot_send_at?->toIso8601String() : null,
            ] : null,
            'draft_cost' => (int) config('crm.credits.reply_draft'),
            'credits_balance' => $credits->balance($user),
            'email_connected' => (bool) $user->emailConnection?->isVerified(),
        ]);
    }

    public function draft(Request $request, CrmReply $reply): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'crm.inbox.draft');
        abort_unless($reply->user_id === $user->id, 404);

        $validated = $request->validate(['instructions' => 'nullable|string|max:500']);

        try {
            $draft = $this->replier->draft($user, $reply, $validated['instructions'] ?? null);
        } catch (InsufficientCreditsException $e) {
            return response()->json(['message' => $e->getMessage()], 402);
        } catch (AIGenerationException|RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['body' => $draft]);
    }

    public function send(Request $request, CrmReply $reply): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'crm.inbox.send');
        abort_unless($reply->user_id === $user->id, 404);

        $validated = $request->validate([
            'subject' => 'required|string|max:200',
            'body' => 'required|string|max:10000',
        ]);

        try {
            $this->replier->send($user, $reply, $validated['subject'], $validated['body']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Reply sent from your own inbox, in the same thread.']);
    }

    public function dismissSuggestion(Request $request, CrmReply $reply): JsonResponse
    {
        $user = $request->user();
        SeatAccess::authorize($user, 'crm.inbox.copilot.dismiss');
        abort_unless($reply->user_id === $user->id, 404);

        $wasQueued = $reply->copilot_status === 'auto_queued';
        $reply->update(['copilot_status' => $wasQueued ? 'drafted' : 'dismissed', 'copilot_send_at' => null]);

        return response()->json(['message' => $wasQueued ? 'Autopilot won\'t send it — the draft is still there for you to edit.' : 'Suggestion dismissed.']);
    }
}
