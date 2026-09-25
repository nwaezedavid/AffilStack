<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Jobs\SendWebhookDelivery;
use App\Models\ApiToken;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
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
 *
 * The API usage prepay wallet (App\Services\ApiWallet\ApiWalletManager)
 * lives on this page too, for the same reason — a third facet of "using the
 * API" rather than a destination of its own. index() only gathers its view
 * data; the wallet's own POST/PATCH actions are ApiWalletController, kept
 * separate since payment/checkout logic is a meaningfully different concern
 * from this controller's plain CRUD.
 */
class ApiAccessController extends Controller
{
    public function index(Request $request, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $user = auth()->user();

        $tokens = $user->apiTokens()->where('type', 'user')->latest()->get();

        // API roadmap item #7 (per-token usage analytics) — a light,
        // 30-day rollup per token from ApiRequestLog (see LogApiRequest).
        // Tokens per user is a small collection, so N+1 here is cheap and
        // keeps the query trivial to read; not worth a join for this.
        $tokens->each(function (ApiToken $token) {
            $recent = $token->requestLogs()->where('created_at', '>=', now()->subDays(30));
            $token->calls_30d = $recent->count();
            $token->spend_cents_30d = (int) $recent->sum('cost_cents');
        });

        $webhookEndpoints = $user->isSeat()
            ? collect()
            : $user->webhookEndpoints()->with(['deliveries' => fn ($query) => $query->latest()->limit(5)])->latest()->get();

        // API usage prepay wallet — owner-only, same reasoning as webhooks
        // above: it belongs to whoever funds the account (billableUser()),
        // not the token/seat that happens to trigger a metered call. See
        // ApiWalletController.
        $billable = $user->billableUser();
        $walletBalanceCents = $billable->api_wallet_balance_cents;
        $walletTransactions = $user->isSeat() ? collect() : $billable->apiWalletTransactions()->limit(10)->get();
        // PayPal is excluded here, not just left unselected: PayPalGateway::
        // chargeSavedToken() always returns unsupported, so offering it as
        // an auto-recharge option would just be a card picker entry that
        // can never actually work.
        $walletPaymentMethods = $billable->paymentMethods->reject(fn ($method) => $method->type === 'paypal');
        $walletEnabledGateways = $gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US');
        $walletTopupPresets = config('api_billing.topup_presets_cents', []);

        // API roadmap item #4 — linked from the Endpoints reference section.
        $openApiUrl = route('api.v1.openapi');

        return view('dashboard.api-access.index', compact(
            'tokens', 'webhookEndpoints', 'billable', 'walletBalanceCents', 'walletTransactions',
            'walletPaymentMethods', 'walletEnabledGateways', 'walletTopupPresets', 'openApiUrl',
        ));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            // API roadmap item #2 (read-only tokens) and item #5 (sandbox
            // mode) — both optional, defaulting to today's behavior (full
            // access, live data) when the form field is left out entirely.
            'scope' => ['nullable', 'string', Rule::in(['full', 'read_only'])],
            'is_sandbox' => ['nullable', 'boolean'],
        ]);

        $result = ApiToken::generate(
            auth()->user(),
            $validated['name'],
            scope: $validated['scope'] ?? 'full',
            isSandbox: $request->boolean('is_sandbox'),
        );

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

    /**
     * API roadmap item #10 — resend a past delivery's exact event/payload
     * as a brand new delivery, reusing SendWebhookDelivery exactly like a
     * fresh event would (same signing, same retry policy) rather than
     * mutating the original row, so the original attempt's own history
     * (attempts, response) stays intact for reference.
     */
    public function replayWebhookDelivery(WebhookDelivery $delivery): RedirectResponse
    {
        abort_unless($delivery->endpoint?->user_id === auth()->id(), 403);

        $replay = WebhookDelivery::create([
            'webhook_endpoint_id' => $delivery->webhook_endpoint_id,
            'event' => $delivery->event,
            'payload' => $delivery->payload,
            'status' => 'pending',
        ]);

        SendWebhookDelivery::dispatch($replay);

        return back()->with('success', 'Replaying delivery — refresh in a moment to see the result.');
    }
}
