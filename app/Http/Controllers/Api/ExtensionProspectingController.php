<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CrmList;
use App\Models\Offer;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;
use App\Services\Extension\ExtensionContactImporter;
use App\Services\Extension\ExtensionOutreachAssistant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;

/**
 * Chrome extension v1.2 (agent 09): the prospecting half of the extension's
 * API — Google Maps businesses → CRM lists, and AI LinkedIn outreach/reply
 * writing. Lives in routes/api.php's extension group next to
 * ExtensionApiController (me/offers/clips, unchanged for v1.1 clients), so
 * it shares the bearer-token auth, read-only scope check and rate limit.
 *
 * Sandbox API tokens are refused: CRM contacts filed here can trigger a
 * list's live campaign autopilot and the AI calls spend real credits.
 */
class ExtensionProspectingController extends Controller
{
    /**
     * The user's CRM lists plus their researched products with each one's
     * default list id — the popup's "Product" and "List" pickers.
     */
    public function lists(Request $request): JsonResponse
    {
        $user = $request->user();

        $lists = $user->crmLists()
            ->withCount('contacts')
            ->orderByDesc('updated_at')
            ->get(['id', 'offer_id', 'name', 'color', 'updated_at']);

        $offers = $user->offers()
            ->where('is_sandbox', false)
            ->latest()
            ->limit(100)
            ->get(['id', 'product_name']);

        $listByOffer = $lists->whereNotNull('offer_id')->keyBy('offer_id');

        return response()->json([
            'lists' => $lists->map(fn (CrmList $list) => [
                'id' => $list->id,
                'name' => $list->name,
                'offer_id' => $list->offer_id,
                'color' => $list->color,
                'contacts_count' => $list->contacts_count,
                'url' => $this->listUrl($list),
            ])->values(),
            'offers' => $offers->map(fn (Offer $offer) => [
                'id' => $offer->id,
                'product_name' => $offer->product_name,
                'list_id' => $listByOffer->get($offer->id)?->id,
            ])->values(),
            'can_use_google_maps' => $user->canUseChannel('google_maps'),
        ]);
    }

    /**
     * Which captured businesses/people are already in the CRM — read-only,
     * so the popup can badge them before anything is added.
     */
    public function lookupContacts(Request $request, ExtensionContactImporter $importer): JsonResponse
    {
        $max = (int) config('extension.max_contacts_per_request', 50);

        $validated = $request->validate([
            'contacts' => ['required', 'array', 'min:1', "max:{$max}"],
            'contacts.*' => ['array'],
        ]);

        return response()->json(['matches' => $importer->lookup($request->user(), $validated['contacts'])]);
    }

    /**
     * Adds one business (top-level fields) or a batch (`contacts: [...]`) to
     * the CRM, filed into the product's own list (CrmList::forOffer) or a
     * list the user picked — always through CrmList::addContact().
     */
    public function storeContacts(Request $request, ExtensionContactImporter $importer): JsonResponse
    {
        $user = $request->user();

        if ($refusal = $this->refuseSandboxOrSeat($request, crm: true)) {
            return $refusal;
        }

        $max = (int) config('extension.max_contacts_per_request', 50);

        $validated = $request->validate([
            'offer_id' => ['nullable', 'integer', 'required_without:list_id'],
            'list_id' => ['nullable', 'integer', 'required_without:offer_id'],
            'contacts' => ['nullable', 'array', 'min:1', "max:{$max}"],
            'name' => ['required_without:contacts', 'nullable', 'string', 'max:255'],
        ], [
            'offer_id.required_without' => 'Pick the product these contacts are for.',
            'list_id.required_without' => 'Pick the product these contacts are for.',
            'name.required_without' => 'Send a contact (name, phone, website…) or a contacts array.',
        ]);

        $items = $validated['contacts'] ?? [$request->except(['offer_id', 'list_id', 'contacts'])];

        $usesMaps = collect($items)->contains(fn ($item) => ! is_array($item) || ($item['source'] ?? 'google_maps') === 'google_maps');

        if ($usesMaps && ! $user->canUseChannel('google_maps')) {
            return response()->json([
                'message' => 'Adding Google Maps businesses isn\'t included in your current plan — upgrade to unlock it.',
                'code' => 'plan_upgrade_required',
                'upgrade_url' => route('billing.index'),
            ], 403);
        }

        $list = $this->resolveList($user, $validated);

        if (! $list) {
            return response()->json([
                'message' => 'That product or list wasn\'t found in your account.',
                'errors' => ['list_id' => ['That product or list wasn\'t found in your account.']],
            ], 422);
        }

        $summary = $importer->import($user, $list, $items);

        if ($summary['limit_reached'] && $summary['created'] === 0 && $summary['duplicates'] === 0) {
            return response()->json([
                'message' => "You've reached your plan's CRM contact limit. Upgrade to add more.",
                'code' => 'contact_limit_reached',
                'upgrade_url' => route('billing.index'),
                ...$summary,
            ], 402);
        }

        return response()->json([
            'message' => $this->summaryMessage($summary, $list),
            'list' => [
                'id' => $list->id,
                'name' => $list->name,
                'offer_id' => $list->offer_id,
                'url' => $this->listUrl($list),
                'campaign_url' => Route::has('crm.campaigns.create') ? route('crm.campaigns.create', ['list' => $list->id]) : null,
            ],
            ...$summary,
        ], $summary['created'] > 0 ? 201 : 200);
    }

    public function linkedinMessage(Request $request, ExtensionOutreachAssistant $assistant, CreditManager $credits): JsonResponse
    {
        if ($refusal = $this->refuseSandboxOrSeat($request)) {
            return $refusal;
        }

        $validated = $request->validate([
            'offer_id' => ['required', 'integer'],
            'profile' => ['required', 'array'],
            'profile.type' => ['nullable', Rule::in(['person', 'company'])],
            'profile.name' => ['required', 'string', 'max:255'],
            'profile.url' => ['nullable', 'string', 'max:2048'],
            'profile.headline' => ['nullable', 'string', 'max:500'],
            'profile.location' => ['nullable', 'string', 'max:255'],
            'profile.about' => ['nullable', 'string', 'max:3000'],
            'profile.current_role' => ['nullable', 'string', 'max:255'],
            'profile.current_company' => ['nullable', 'string', 'max:255'],
            'profile.experience' => ['nullable', 'array', 'max:6'],
            'profile.experience.*' => ['string', 'max:500'],
            'profile.industry' => ['nullable', 'string', 'max:255'],
            'profile.company_size' => ['nullable', 'string', 'max:255'],
            'profile.company_description' => ['nullable', 'string', 'max:3000'],
            'profile.website' => ['nullable', 'string', 'max:2048'],
            'profile.followers' => ['nullable', 'string', 'max:100'],
        ]);

        $offer = $this->accessibleOffer($request->user(), (int) $validated['offer_id']);

        if (! $offer) {
            return $this->offerNotFound();
        }

        try {
            $pack = $assistant->linkedinMessage($request->user(), $offer, $validated['profile']);
        } catch (InsufficientCreditsException) {
            return $this->insufficientCredits($request->user(), ExtensionOutreachAssistant::messageCost(), $credits);
        } catch (AIGenerationException $e) {
            return $this->aiFailed($e);
        }

        return response()->json([
            ...$pack,
            'connection_note_limit' => (int) config('extension.linkedin_connection_note_limit', 200),
            'credits_balance' => $credits->balance($request->user()),
        ]);
    }

    public function linkedinReply(Request $request, ExtensionOutreachAssistant $assistant, CreditManager $credits): JsonResponse
    {
        if ($refusal = $this->refuseSandboxOrSeat($request)) {
            return $refusal;
        }

        $maxMessages = (int) config('extension.reply_max_messages', 20);

        $validated = $request->validate([
            'offer_id' => ['nullable', 'integer'],
            'channel' => ['nullable', Rule::in(['linkedin', 'email', 'whatsapp', 'other'])],
            'prospect_name' => ['nullable', 'string', 'max:255'],
            'messages' => ['nullable', 'array', 'min:1', 'required_without:their_message'],
            'messages.*.role' => ['required', Rule::in(['me', 'them'])],
            'messages.*.text' => ['required', 'string', 'max:5000'],
            'messages.*.sender' => ['nullable', 'string', 'max:255'],
            'their_message' => ['nullable', 'string', 'max:5000', 'required_without:messages'],
        ], [
            'their_message.required_without' => 'Paste the message you want to reply to.',
            'messages.required_without' => 'Paste the message you want to reply to.',
        ]);

        $messages = isset($validated['messages'])
            ? array_slice(array_values($validated['messages']), -$maxMessages)
            : [['role' => 'them', 'text' => $validated['their_message'], 'sender' => $validated['prospect_name'] ?? null]];

        if (! collect($messages)->contains('role', 'them')) {
            return response()->json([
                'message' => 'There\'s no message from the prospect to reply to yet.',
                'errors' => ['messages' => ['There\'s no message from the prospect to reply to yet.']],
            ], 422);
        }

        $offer = null;

        if (! empty($validated['offer_id'])) {
            $offer = $this->accessibleOffer($request->user(), (int) $validated['offer_id']);

            if (! $offer) {
                return $this->offerNotFound();
            }
        }

        try {
            $suggestion = $assistant->suggestReply(
                $request->user(),
                $offer,
                $messages,
                $validated['channel'] ?? 'linkedin',
                $validated['prospect_name'] ?? null,
            );
        } catch (InsufficientCreditsException) {
            return $this->insufficientCredits($request->user(), ExtensionOutreachAssistant::replyCost(), $credits);
        } catch (AIGenerationException $e) {
            return $this->aiFailed($e);
        }

        return response()->json([
            ...$suggestion,
            'credits_balance' => $credits->balance($request->user()),
        ]);
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    protected function resolveList(User $user, array $validated): ?CrmList
    {
        if (! empty($validated['list_id'])) {
            return $user->crmLists()->whereKey($validated['list_id'])->first();
        }

        $offer = $user->offers()->where('is_sandbox', false)->whereKey($validated['offer_id'])->first();

        return $offer ? CrmList::forOffer($offer) : null;
    }

    protected function accessibleOffer(User $user, int $offerId): ?Offer
    {
        $offer = Offer::where('is_sandbox', false)->find($offerId);

        return $offer && $offer->isAccessibleBy($user) ? $offer : null;
    }

    /**
     * A sandbox token never touches real CRM data or credits; the CRM is
     * owner-only (team seats are excluded, as on the dashboard).
     */
    protected function refuseSandboxOrSeat(Request $request, bool $crm = false): ?JsonResponse
    {
        if ($request->attributes->get('apiToken')?->is_sandbox) {
            return response()->json([
                'message' => 'Sandbox tokens can\'t be used with the browser extension — generate a token on the Browser Extension page.',
                'code' => 'sandbox_token',
            ], 403);
        }

        if ($crm && $request->user()->isSeat()) {
            return response()->json([
                'message' => 'Only the account owner can add contacts to the CRM.',
                'code' => 'owner_only',
            ], 403);
        }

        return null;
    }

    protected function insufficientCredits(User $user, int $needed, CreditManager $credits): JsonResponse
    {
        $balance = $credits->balance($user);

        return response()->json([
            'message' => "You need {$needed} credits for this and have {$balance}. Top up or upgrade your plan to keep going.",
            'code' => 'insufficient_credits',
            'credits_required' => $needed,
            'credits_balance' => $balance,
            'top_up_url' => route('billing.index'),
        ], 402);
    }

    protected function aiFailed(AIGenerationException $e): JsonResponse
    {
        return response()->json([
            'message' => "Couldn't write that right now: {$e->getMessage()} No credits were used.",
            'code' => 'ai_unavailable',
        ], 503);
    }

    protected function offerNotFound(): JsonResponse
    {
        return response()->json([
            'message' => 'That product wasn\'t found in your account.',
            'errors' => ['offer_id' => ['That product wasn\'t found in your account.']],
        ], 422);
    }

    protected function listUrl(CrmList $list): string
    {
        return Route::has('crm.lists.show') ? route('crm.lists.show', $list) : route('crm.index');
    }

    /**
     * @param  array{created: int, duplicates: int, failed: int, added_to_list: int, limit_reached: bool}  $summary
     */
    protected function summaryMessage(array $summary, CrmList $list): string
    {
        $parts = [];

        if ($summary['created'] > 0) {
            $parts[] = $summary['created'] === 1 ? '1 new contact added' : "{$summary['created']} new contacts added";
        }

        if ($summary['duplicates'] > 0) {
            $parts[] = $summary['duplicates'] === 1 ? '1 was already in your CRM' : "{$summary['duplicates']} were already in your CRM";
        }

        if ($summary['failed'] > 0) {
            $parts[] = $summary['limit_reached'] ? "{$summary['failed']} skipped (contact limit reached)" : "{$summary['failed']} couldn't be added";
        }

        return ($parts === [] ? 'Nothing to add' : ucfirst(implode(', ', $parts))).' — filed in '.$list->name.'.';
    }
}
