@php
    $costs = collect(config('api_billing.costs', []));
@endphp

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">Usage & limits</h3>
    <p class="text-sm text-ink-600">How much you're using the API, what it costs, and the limits for your plan.</p>
</div>

<div class="grid grid-cols-2 gap-3 xl:grid-cols-4">
    <x-ui.stat label="Rate limit" :value="$currentRateLimit.'/min'" icon="bolt" tone="brand" hint="per token, on your plan" />
    <x-ui.stat label="Calls (30 days)" :value="number_format($usage['calls_30d'])" icon="arrows-right-left" tone="violet" hint="across your tokens" />
    <x-ui.stat label="Errors (30 days)" :value="number_format($usage['errors_30d'])" icon="exclamation-triangle" :tone="$usage['errors_30d'] ? 'rose' : 'emerald'" hint="4xx and 5xx responses" />
    <x-ui.stat label="Spend (30 days)" :value="'$'.number_format($usage['spend_cents_30d'] / 100, 2)" icon="banknotes" tone="gold" hint="metered calls only" />
</div>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
    <x-ui.card title="Rate limits by plan" description="Requests per minute, per token. Go over and you get a 429 with a Retry-After header." icon="clock" padding="p-0">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-line">
                @foreach ($rateLimits as $slug => $limit)
                    @continue($slug === 'default')
                    <tr @class(['bg-brand-50/60' => $slug === $currentPlanSlug])>
                        <td class="px-5 py-2.5 text-navy-900">
                            {{ $planNames[$slug] ?? ucfirst($slug) }}
                            @if ($slug === $currentPlanSlug)
                                <span class="ml-1 rounded-full bg-brand-600 px-2 py-0.5 text-[10px] font-semibold uppercase text-white">Your plan</span>
                            @endif
                        </td>
                        <td class="px-5 py-2.5 text-right font-mono text-sm text-navy-900">{{ $limit }} / min</td>
                    </tr>
                @endforeach
                <tr>
                    <td class="px-5 py-2.5 text-ink-600">No active plan</td>
                    <td class="px-5 py-2.5 text-right font-mono text-sm text-ink-600">{{ $rateLimits['default'] ?? 60 }} / min</td>
                </tr>
            </tbody>
        </table>
    </x-ui.card>

    <x-ui.card title="What costs money" description="Reading data is always free. Only calls that run real AI work are metered." icon="banknotes" padding="p-0">
        <table class="w-full text-sm">
            <tbody class="divide-y divide-line">
                @foreach ($costs as $routeName => $cents)
                    <tr>
                        <td class="px-5 py-2.5 font-mono text-xs text-navy-900">{{ $routePaths[$routeName] ?? $routeName }}</td>
                        <td class="px-5 py-2.5 text-right text-sm font-semibold text-navy-900">${{ number_format($cents / 100, 2) }} <span class="text-xs font-normal text-ink-600">/ call</span></td>
                    </tr>
                @endforeach
                <tr>
                    <td class="px-5 py-2.5 text-ink-600">Every other endpoint</td>
                    <td class="px-5 py-2.5 text-right text-sm font-semibold text-emerald-700">Free</td>
                </tr>
            </tbody>
        </table>
        <p class="border-t border-line px-5 py-3 text-xs text-ink-600">
            Metered calls draw from the API wallet{{ $isSeat ? ' your account owner funds' : ' below' }}, separately from your plan's monthly credits. Sandbox tokens are never charged, and a call that fails is refunded automatically.
        </p>
    </x-ui.card>
</div>

{{-- API usage prepay wallet — owner-only, see ApiWalletController --}}
@if (! $isSeat)
    <x-ui.card id="api-wallet" title="API wallet" description="A prepaid balance just for pay-per-call API usage — separate from your plan's monthly credits." icon="wallet">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Balance</p>
                <p class="font-display text-3xl font-semibold text-navy-900 tabular-nums">${{ number_format($walletBalanceCents / 100, 2) }}</p>
            </div>
            @if ($walletBalanceCents <= config('api_billing.low_balance_threshold_cents'))
                <div class="rounded-xl bg-amber-50 px-3 py-2 text-xs text-amber-800 ring-1 ring-amber-200 sm:max-w-xs">
                    Running low — top up below, or turn on auto-recharge so metered calls don't start getting blocked.
                </div>
            @endif
        </div>

        <div class="mt-6 grid gap-6 md:grid-cols-2">
            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-600 mb-2">Top up</h4>
                @if (empty($walletEnabledGateways))
                    <p class="text-sm text-ink-400">Payments are temporarily unavailable — please try again shortly.</p>
                @else
                    <form method="POST" action="{{ route('api-access.wallet.checkout') }}" class="space-y-3">
                        @csrf
                        <div class="flex flex-wrap gap-2">
                            @foreach ($walletTopupPresets as $preset)
                                <label class="cursor-pointer">
                                    <input type="radio" name="amount_cents" value="{{ $preset }}" class="peer sr-only" {{ $loop->first ? 'checked' : '' }} required>
                                    <span class="block rounded-full border border-line px-4 py-1.5 text-sm text-navy-900 transition peer-checked:border-navy-900 peer-checked:bg-navy-900 peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500">
                                        ${{ number_format($preset / 100) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @if (count($walletEnabledGateways) > 1)
                            <label class="block text-xs text-ink-600">Pay with
                                <select name="gateway" class="mt-1 w-full rounded-xl border border-line px-2 py-1.5 text-sm">
                                    @foreach ($walletEnabledGateways as $gateway)
                                        <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @elseif (count($walletEnabledGateways) === 1)
                            <input type="hidden" name="gateway" value="{{ $walletEnabledGateways[0]->key() }}">
                        @endif
                        <x-ui.button variant="primary" icon="plus">Add to wallet</x-ui.button>
                        <p class="text-xs text-ink-400">Paying with a new card saves it automatically — pick it on the right to enable automatic top-ups.</p>
                    </form>
                @endif
            </div>

            <div>
                <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-600 mb-2">Auto-recharge</h4>
                @if ($walletPaymentMethods->isEmpty())
                    <p class="text-sm text-ink-400">Top up once with a card to unlock automatic recharging.</p>
                @else
                    <form method="POST" action="{{ route('api-access.wallet.settings') }}" class="space-y-3">
                        @csrf
                        @method('PATCH')
                        <label class="flex items-center gap-2 text-sm text-navy-900">
                            <input type="checkbox" name="auto_recharge_enabled" value="1" class="accent-brand-600" {{ $billable->api_wallet_auto_recharge_enabled ? 'checked' : '' }}>
                            Automatically top up when balance runs low
                        </label>
                        <div class="grid grid-cols-2 gap-2">
                            <label class="block text-xs text-ink-600">When balance drops below
                                <select name="auto_recharge_threshold_cents" class="mt-1 w-full rounded-xl border border-line px-2 py-1.5 text-sm">
                                    @foreach ([100 => '$1', 200 => '$2', 500 => '$5', 1000 => '$10', 2500 => '$25', 5000 => '$50', 10000 => '$100'] as $cents => $label)
                                        <option value="{{ $cents }}" {{ (int) $billable->api_wallet_auto_recharge_threshold_cents === $cents ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block text-xs text-ink-600">Recharge this much
                                <select name="auto_recharge_amount_cents" class="mt-1 w-full rounded-xl border border-line px-2 py-1.5 text-sm">
                                    @foreach ([1000 => '$10', 2500 => '$25', 5000 => '$50', 10000 => '$100', 25000 => '$250', 50000 => '$500'] as $cents => $label)
                                        <option value="{{ $cents }}" {{ (int) $billable->api_wallet_auto_recharge_amount_cents === $cents ? 'selected' : '' }}>{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>
                        </div>
                        <label class="block text-xs text-ink-600">Card to charge
                            <select name="payment_method_id" class="mt-1 w-full rounded-xl border border-line px-2 py-1.5 text-sm">
                                @foreach ($walletPaymentMethods as $method)
                                    <option value="{{ $method->id }}" {{ $billable->api_wallet_payment_method_id === $method->id ? 'selected' : '' }}>{{ $method->display() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <x-ui.button variant="secondary">Save auto-recharge settings</x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        @if ($walletTransactions->isNotEmpty())
            <div class="mt-6">
                <h4 class="text-xs font-semibold uppercase tracking-wide text-ink-600 mb-2">Recent wallet activity</h4>
                <div class="overflow-hidden rounded-xl border border-line">
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead class="bg-surface-muted text-left text-[11px] font-mono uppercase tracking-wide text-ink-400">
                                <tr>
                                    <th class="px-4 py-2">Type</th>
                                    <th class="px-4 py-2">Description</th>
                                    <th class="px-4 py-2 text-right">Amount</th>
                                    <th class="px-4 py-2 text-right">Balance after</th>
                                    <th class="px-4 py-2">Date</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-line">
                                @foreach ($walletTransactions as $transaction)
                                    <tr>
                                        <td class="px-4 py-2.5">
                                            <span @class([
                                                'rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-red-50 text-red-700' => $transaction->type === 'usage',
                                                'bg-emerald-50 text-emerald-700' => in_array($transaction->type, ['topup', 'auto_recharge'], true),
                                                'bg-surface-muted text-ink-600' => $transaction->type === 'refund',
                                            ])>{{ $transaction->type === 'topup' ? 'Top-up' : ucfirst(str_replace('_', ' ', $transaction->type)) }}</span>
                                        </td>
                                        <td class="px-4 py-2.5 text-ink-600">{{ $transaction->description ?? '—' }}</td>
                                        <td class="px-4 py-2.5 text-right font-mono {{ $transaction->amount_cents < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                                            {{ $transaction->amount_cents < 0 ? '-' : '+' }}${{ number_format(abs($transaction->amount_cents) / 100, 2) }}
                                        </td>
                                        <td class="px-4 py-2.5 text-right font-mono text-ink-600">${{ number_format($transaction->balance_after_cents / 100, 2) }}</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap text-ink-600">{{ $transaction->created_at->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </x-ui.card>
@endif

<x-ui.card title="Recent requests" description="Your last 25 API calls, newest first." icon="list-bullet" padding="p-0">
    @if ($requestLogs->isEmpty())
        <p class="px-5 py-8 text-center text-sm text-ink-600">No API calls yet. Make your first one from the Quick start tab — it will show up here.</p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-left text-[11px] font-mono uppercase tracking-wide text-ink-400">
                    <tr>
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Request</th>
                        <th class="px-4 py-2">Token</th>
                        <th class="px-4 py-2">Status</th>
                        <th class="px-4 py-2 text-right">Cost</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($requestLogs as $log)
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-ink-600">{{ $log->created_at->diffForHumans() }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap font-mono text-xs text-navy-900">{{ $routePaths[$log->route] ?? $log->method.' '.($log->route ?: '—') }}</td>
                            <td class="px-4 py-2.5 whitespace-nowrap text-xs text-ink-600">{{ $log->token?->name ?? 'Revoked token' }}</td>
                            <td class="px-4 py-2.5">
                                <span @class([
                                    'rounded-full px-2 py-0.5 font-mono text-xs font-semibold ring-1 ring-inset',
                                    'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $log->status_code < 300,
                                    'bg-amber-50 text-amber-700 ring-amber-600/20' => $log->status_code >= 300 && $log->status_code < 500,
                                    'bg-rose-50 text-rose-700 ring-rose-600/20' => $log->status_code >= 500,
                                ])>{{ $log->status_code }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right font-mono text-xs text-ink-600">{{ $log->cost_cents ? '$'.number_format($log->cost_cents / 100, 2) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>
