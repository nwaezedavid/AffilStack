@extends('layouts.app')

@section('title', 'API Access')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Connect your own tools to AffilStack — pull your offers, generated content, and CRM contacts, or push new
        contacts in automatically. Generate a token below and use it as a Bearer token against the endpoints listed
        further down.
    </p>

    {{-- Tokens --}}
    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Your tokens</h3>
        <p class="text-sm text-ink-600 mb-4">Give each integration its own token so you can revoke one without affecting the others. A token created here also works for the browser extension, and vice versa.</p>

        <form method="POST" action="{{ route('api-access.tokens.store') }}" class="mb-5 space-y-2">
            @csrf
            <div class="flex items-center gap-2 flex-wrap">
                <input name="name" required placeholder="e.g. Zapier" class="rounded-md border border-line px-3 py-2 text-sm">
                <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Generate token</button>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-ink-600">
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="scope" value="full" checked>
                    Full access
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="radio" name="scope" value="read_only">
                    Read-only — GET requests only, can never write data or spend the wallet
                </label>
                <label class="flex items-center gap-1.5">
                    <input type="checkbox" name="is_sandbox" value="1">
                    Sandbox token — test data only, never bills your wallet or credits
                </label>
            </div>
        </form>

        @if ($tokens->isEmpty())
            <p class="text-sm text-ink-500">No tokens yet — generate one above to get started.</p>
        @else
            <div class="border border-line rounded-md overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                        <tr>
                            <th class="text-left px-4 py-2">Name</th>
                            <th class="text-left px-4 py-2">Type</th>
                            <th class="text-left px-4 py-2">Last 30 days</th>
                            <th class="text-left px-4 py-2">Last used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-ink-900">{{ $token->name }}</td>
                                <td class="px-4 py-2.5">
                                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-surface-muted text-ink-600">{{ $token->isReadOnly() ? 'Read-only' : 'Full access' }}</span>
                                    @if ($token->is_sandbox)
                                        <span class="text-xs font-mono px-2 py-0.5 rounded bg-amber-50 text-amber-700">Sandbox</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-ink-600">
                                    {{ $token->calls_30d }} call{{ $token->calls_30d === 1 ? '' : 's' }}
                                    @if ($token->spend_cents_30d > 0)
                                        &middot; ${{ number_format($token->spend_cents_30d / 100, 2) }}
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-ink-600">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('api-access.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    </div>

    {{-- API usage prepay wallet — owner-only, see ApiWalletController --}}
    @if (! auth()->user()->isSeat())
        <div class="bg-surface border border-line rounded-lg p-5 mb-6">
            <div class="flex items-start justify-between gap-4 flex-wrap mb-4">
                <div>
                    <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">API wallet</h3>
                    <p class="text-sm text-ink-600 max-w-md">
                        A prepaid balance just for pay-per-call API usage — separate from your plan's monthly
                        credits above. Right now only <code class="font-mono text-xs bg-surface-muted px-1 py-0.5 rounded">POST /offers</code>
                        draws from it, at $0.75 a call.
                    </p>
                </div>
                <div class="text-right shrink-0">
                    <div class="text-xs uppercase tracking-wide text-ink-400 font-mono">Balance</div>
                    <div class="font-mono text-2xl font-semibold text-navy-900">${{ number_format($walletBalanceCents / 100, 2) }}</div>
                </div>
            </div>

            @if ($walletBalanceCents <= config('api_billing.low_balance_threshold_cents'))
                <div class="bg-amber-50 text-amber-800 text-xs rounded-md px-3 py-2 mb-4">
                    Running low — top up below, or turn on auto-recharge so metered calls don't start getting blocked.
                </div>
            @endif

            @if (session('success') && str_contains(session('success'), 'wallet'))
                <div class="bg-emerald-50 text-emerald-800 text-xs rounded-md px-3 py-2 mb-4">{{ session('success') }}</div>
            @endif
            @if (session('error'))
                <div class="bg-red-50 text-red-700 text-xs rounded-md px-3 py-2 mb-4">{{ session('error') }}</div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-5">
                {{-- Top up --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">Top up</h4>
                    @if (empty($walletEnabledGateways))
                        <p class="text-sm text-ink-400">Payments are temporarily unavailable — please try again shortly.</p>
                    @else
                        <form method="POST" action="{{ route('api-access.wallet.checkout') }}" class="space-y-3">
                            @csrf
                            <div class="flex flex-wrap gap-2">
                                @foreach ($walletTopupPresets as $preset)
                                    <label class="cursor-pointer">
                                        <input type="radio" name="amount_cents" value="{{ $preset }}" class="peer sr-only" {{ $loop->first ? 'checked' : '' }} required>
                                        <span class="block rounded-md border border-line px-3 py-1.5 text-sm text-ink-700 peer-checked:bg-navy-900 peer-checked:text-white peer-checked:border-navy-900 transition">
                                            ${{ number_format($preset / 100) }}
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @if (count($walletEnabledGateways) > 1)
                                <select name="gateway" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                                    @foreach ($walletEnabledGateways as $gateway)
                                        <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                                    @endforeach
                                </select>
                            @elseif (count($walletEnabledGateways) === 1)
                                <input type="hidden" name="gateway" value="{{ $walletEnabledGateways[0]->key() }}">
                            @endif
                            <button class="rounded-md bg-navy-900 text-white text-sm px-4 py-2 hover:bg-navy-800 transition">
                                Add to wallet
                            </button>
                            <p class="text-xs text-ink-400">Paying with a new card saves it automatically — pick it below to enable auto-recharge.</p>
                        </form>
                    @endif
                </div>

                {{-- Auto-recharge --}}
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">Auto-recharge</h4>
                    @if ($walletPaymentMethods->isEmpty())
                        <p class="text-sm text-ink-400">Top up once with a card above to unlock automatic recharging.</p>
                    @else
                        <form method="POST" action="{{ route('api-access.wallet.settings') }}" class="space-y-3">
                            @csrf
                            @method('PATCH')
                            <label class="flex items-center gap-2 text-sm text-ink-700">
                                <input type="checkbox" name="auto_recharge_enabled" value="1" {{ $billable->api_wallet_auto_recharge_enabled ? 'checked' : '' }}>
                                Automatically top up when balance runs low
                            </label>

                            <div class="grid grid-cols-2 gap-2">
                                <div>
                                    <label class="block text-xs text-ink-500 mb-1">When balance drops below</label>
                                    <select name="auto_recharge_threshold_cents" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                                        @foreach ([100 => '$1', 200 => '$2', 500 => '$5', 1000 => '$10', 2500 => '$25', 5000 => '$50', 10000 => '$100'] as $cents => $label)
                                            <option value="{{ $cents }}" {{ (int) $billable->api_wallet_auto_recharge_threshold_cents === $cents ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs text-ink-500 mb-1">Recharge this much</label>
                                    <select name="auto_recharge_amount_cents" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                                        @foreach ([1000 => '$10', 2500 => '$25', 5000 => '$50', 10000 => '$100', 25000 => '$250', 50000 => '$500'] as $cents => $label)
                                            <option value="{{ $cents }}" {{ (int) $billable->api_wallet_auto_recharge_amount_cents === $cents ? 'selected' : '' }}>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs text-ink-500 mb-1">Card to charge</label>
                                <select name="payment_method_id" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                                    @foreach ($walletPaymentMethods as $method)
                                        <option value="{{ $method->id }}" {{ $billable->api_wallet_payment_method_id === $method->id ? 'selected' : '' }}>{{ $method->display() }}</option>
                                    @endforeach
                                </select>
                            </div>

                            <button class="rounded-md border border-line text-ink-900 text-sm px-4 py-2 hover:bg-surface-muted transition">
                                Save auto-recharge settings
                            </button>
                        </form>
                    @endif
                </div>
            </div>

            {{-- Recent activity --}}
            @if ($walletTransactions->isNotEmpty())
                <div>
                    <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-500 mb-2">Recent activity</h4>
                    <div class="border border-line rounded-md overflow-hidden">
                        <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                                <tr>
                                    <th class="text-left px-4 py-2">Type</th>
                                    <th class="text-left px-4 py-2">Description</th>
                                    <th class="text-right px-4 py-2">Amount</th>
                                    <th class="text-right px-4 py-2">Balance after</th>
                                    <th class="text-left px-4 py-2">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($walletTransactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <span @class([
                                                'text-xs font-mono px-2 py-0.5 rounded',
                                                'bg-red-50 text-red-700' => $transaction->type === 'usage',
                                                'bg-emerald-50 text-emerald-700' => in_array($transaction->type, ['topup', 'auto_recharge'], true),
                                                'bg-surface-muted text-ink-500' => $transaction->type === 'refund',
                                            ])>{{ $transaction->type === 'topup' ? 'Top-up' : ucfirst(str_replace('_', ' ', $transaction->type)) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-ink-600">{{ $transaction->description ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono {{ $transaction->amount_cents < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                            {{ $transaction->amount_cents < 0 ? '-' : '+' }}${{ number_format(abs($transaction->amount_cents) / 100, 2) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-ink-600">${{ number_format($transaction->balance_after_cents / 100, 2) }}</td>
                                        <td class="px-4 py-2.5 text-ink-500">{{ $transaction->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    @endif

    {{-- Outbound webhooks (audit gap #7) — owner-only, see ApiAccessController --}}
    @if (! auth()->user()->isSeat())
        <div class="bg-surface border border-line rounded-lg p-5 mb-6">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Outbound webhooks</h3>
            <p class="text-sm text-ink-600 mb-4">
                Get a signed HTTP POST the moment something happens, instead of polling the API above. Each delivery
                carries an <code class="font-mono text-xs bg-surface-muted px-1 py-0.5 rounded">X-AffilStack-Signature</code>
                header — an HMAC-SHA256 of the raw request body using the endpoint's secret below — so you can verify
                it actually came from AffilStack.
            </p>

            <form method="POST" action="{{ route('api-access.webhooks.store') }}" class="mb-5 space-y-2">
                @csrf
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="url" name="url" required placeholder="https://your-app.example.com/webhooks/affilstack"
                           class="rounded-md border border-line px-3 py-2 text-sm flex-1 min-w-[16rem]">
                    <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Add endpoint</button>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-ink-700">
                    @foreach (config('webhooks.events') as $event => $label)
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" name="events[]" value="{{ $event }}" checked>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('url')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('events')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>

            @if ($webhookEndpoints->isEmpty())
                <p class="text-sm text-ink-500">No webhook endpoints yet — add one above to get started.</p>
            @else
                <div class="space-y-3">
                    @foreach ($webhookEndpoints as $endpoint)
                        <div class="border border-line rounded-md p-3">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <p class="text-sm font-medium text-ink-900 break-all">{{ $endpoint->url }}</p>
                                    <p class="text-xs text-ink-500 mt-0.5">
                                        {{ collect($endpoint->events)->map(fn ($e) => config('webhooks.events')[$e] ?? $e)->implode(', ') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'text-xs font-mono px-2 py-0.5 rounded',
                                        'bg-emerald-50 text-emerald-700' => $endpoint->is_active,
                                        'bg-surface-muted text-ink-500' => ! $endpoint->is_active,
                                    ])>{{ $endpoint->is_active ? 'Active' : 'Disabled' }}</span>
                                    <form method="POST" action="{{ route('api-access.webhooks.toggle', $endpoint) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs text-brand-600 hover:text-brand-700">{{ $endpoint->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('api-access.webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Remove this webhook endpoint?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-xs text-ink-500 mt-2">
                                Signing secret: <code class="font-mono bg-surface-muted px-1.5 py-0.5 rounded">{{ $endpoint->secret }}</code>
                                &middot; Last delivered: {{ $endpoint->last_triggered_at?->diffForHumans() ?? 'Never' }}
                            </p>
                            @if ($endpoint->deliveries->isNotEmpty())
                                <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                    @foreach ($endpoint->deliveries as $delivery)
                                        <span title="{{ $delivery->event }} — {{ $delivery->created_at->diffForHumans() }}" @class([
                                            'text-[11px] font-mono px-1.5 py-0.5 rounded',
                                            'bg-emerald-50 text-emerald-700' => $delivery->status === 'delivered',
                                            'bg-red-50 text-red-700' => $delivery->status === 'failed',
                                            'bg-amber-50 text-amber-700' => $delivery->status === 'pending',
                                        ])>{{ $delivery->event }}: {{ $delivery->status }}</span>
                                        {{-- API roadmap item #10 --}}
                                        <form method="POST" action="{{ route('api-access.webhooks.deliveries.replay', $delivery) }}">
                                            @csrf
                                            <button class="text-[11px] text-brand-600 hover:text-brand-700 underline">Replay</button>
                                        </form>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

    {{-- Reference --}}
    <div class="bg-surface border border-line rounded-lg p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap mb-2">
            <h3 class="font-display font-semibold text-sm text-navy-900">Endpoints</h3>
            <a href="{{ $openApiUrl }}" class="text-xs text-brand-600 hover:text-brand-700 underline shrink-0">
                Download OpenAPI spec
            </a>
        </div>
        <p class="text-sm text-ink-600 mb-4">
            Base URL: <code class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">{{ url('/api/v1') }}</code>
            &middot; Auth header: <code class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">Authorization: Bearer &lt;token&gt;</code>
            &middot; 60&ndash;300 requests/minute per token, based on your plan.
        </p>
        <p class="text-xs text-ink-400 mb-1">
            Everything below is free except <code class="font-mono bg-surface-muted px-1 py-0.5 rounded">POST /offers</code>,
            which costs $0.75 a call
            @if (auth()->user()->isSeat())
                from the account's API wallet — ask the account owner to keep it topped up.
            @else
                from your API wallet above.
            @endif
        </p>
        <p class="text-xs text-ink-400 mb-4">
            <code class="font-mono bg-surface-muted px-1 py-0.5 rounded">POST /offers</code> and
            <code class="font-mono bg-surface-muted px-1 py-0.5 rounded">POST /crm-contacts</code> accept an optional
            <code class="font-mono bg-surface-muted px-1 py-0.5 rounded">Idempotency-Key</code> header to safely retry
            a call. A read-only token can use every GET below but no write. A sandbox token never touches real
            balances — see the token options above.
        </p>
        <div class="border border-line rounded-md overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2">Method</th>
                        <th class="text-left px-4 py-2">Path</th>
                        <th class="text-left px-4 py-2">What it does</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line font-mono text-xs">
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/me</td><td class="px-4 py-2 font-sans text-ink-600">Your account, plan, and credit balance</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/offers</td><td class="px-4 py-2 font-sans text-ink-600">List your offers</td></tr>
                    <tr><td class="px-4 py-2">POST</td><td class="px-4 py-2">/offers</td><td class="px-4 py-2 font-sans text-ink-600">Queue new offer research</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/offers/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">A single offer</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/generations</td><td class="px-4 py-2 font-sans text-ink-600">List generated content (filter by offer_id, module)</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/generations/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">A single piece of generated content</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/crm-contacts</td><td class="px-4 py-2 font-sans text-ink-600">List your CRM contacts</td></tr>
                    <tr><td class="px-4 py-2">POST</td><td class="px-4 py-2">/crm-contacts</td><td class="px-4 py-2 font-sans text-ink-600">Add a new contact</td></tr>
                    <tr><td class="px-4 py-2">POST</td><td class="px-4 py-2">/crm-contacts/bulk</td><td class="px-4 py-2 font-sans text-ink-600">Add up to 100 contacts in one call</td></tr>
                    <tr><td class="px-4 py-2">PATCH</td><td class="px-4 py-2">/crm-contacts/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">Update a contact</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/referrals/summary</td><td class="px-4 py-2 font-sans text-ink-600">Your referral link and commission totals</td></tr>
                </tbody>
            </table>
            </div>
        </div>
    </div>
@endsection
