<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use App\Models\WebhookEndpoint;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Task #6 (general API + team automation): the dashboard side of the
 * general-purpose API. Reuses ApiToken exactly as the browser extension
 * does (ExtensionController) — a token created here works there and vice
 * versa, since every /v1/* endpoint scopes its own response to the
 * token's owner rather than the page that minted it. Deliberately open to
 * team seats (see config('agency.seat_allowed_routes')), unlike the
 * extension: a seat's own token naturally only ever sees what that seat
 * can already see (visibleOffers()/visibleGenerations() for a shared-plan
 * seat, its one assigned offer for an isolated one).
 *
 * Audit gap #7 added the other half of this same feature area — outbound
 * webhooks (App\Services\Webhooks\WebhookDispatcher) — to this same
 * controller/page rather than a new nav item, since it's the push
 * counterpart to the pull-only /v1/* API already managed here. Unlike
 * tokens, webhook management is owner-only: referrals and earnings (two of
 * the four subscribable events) aren't something a team seat has its own
 * view of in the first place.
 */
class ApiAccessController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $tokens = $user->apiTokens()->where('type', 'user')->latest()->get();
        $webhookEndpoints = $user->isSeat()
            ? collect()
            : $user->webhookEndpoints()->with(['deliveries' => fn ($query) => $query->latest()->limit(5)])->latest()->get();

        return view('dashboard.api-access.index', compact('tokens', 'webhookEndpoints'));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = ApiToken::generate(auth()->user(), $validated['name']);

        return back()->with(
            'success',
            "Token created — copy it now, it won't be shown again:\n{$result['plainText']}"
        );
    }

    public function revokeToken(ApiToken $token): RedirectResponse
    {
        abort_unless($token->user_id === auth()->id(), 403);

        $token->delete();

        return back()->with('success', 'Token revoked — anything using it will get a 401 from now on.');
    }

    public function storeWebhook(Request $request): RedirectResponse
    {
        abort_if($request->user()->isSeat(), 403);

        $validated = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', Rule::in(array_keys(config('webhooks.events')))],
        ]);

        $request->user()->webhookEndpoints()->create([
            'url' => $validated['url'],
            'events' => $validated['events'],
            'secret' => WebhookEndpoint::generateSecret(),
            'is_active' => true,
        ]);

        return back()->with('success', 'Webhook endpoint added.');
    }

    public function toggleWebhook(WebhookEndpoint $webhook): RedirectResponse
    {
        abort_unless($webhook->user_id === auth()->id(), 403);

        $webhook->update(['is_active' => ! $webhook->is_active]);

        return back()->with('success', $webhook->is_active ? 'Webhook enabled.' : 'Webhook disabled.');
    }

    public function destroyWebhook(WebhookEndpoint $webhook): RedirectResponse
    {
        abort_unless($webhook->user_id === auth()->id(), 403);

        $webhook->delete();

        return back()->with('success', 'Webhook endpoint removed.');
    }
}
