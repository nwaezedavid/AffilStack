@extends('layouts.app')

@section('title', 'Wallet')

@section('content')
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Wallet balance</div>
            <div class="font-mono text-3xl font-semibold text-navy-900" data-wallet-balance>{{ $wallet->formattedBalance() }}</div>
            <p class="text-xs text-ink-600 mt-2">Use it to buy credit packs and pay plan renewals. Wallet money can't be withdrawn.</p>
        </div>

        <div class="bg-surface border border-line rounded-lg p-5 lg:col-span-2">
            <h2 class="font-display font-semibold text-sm text-navy-900 mb-3">Top up</h2>
            @if (empty($enabledGateways))
                <p class="text-sm text-ink-600">Payments are temporarily unavailable — please try again shortly.</p>
            @else
                <form method="POST" action="{{ route('wallet.top-up') }}" class="flex flex-col sm:flex-row sm:items-end gap-3">
                    @csrf
                    <div class="sm:w-40">
                        <label for="amount" class="block text-sm font-medium text-ink-900 mb-1">Amount ({{ $wallet->currency }})</label>
                        <input id="amount" name="amount" type="number" inputmode="decimal" step="0.01"
                               min="{{ $minTopup / 100 }}" max="{{ $maxTopup / 100 }}" value="{{ old('amount', 25) }}" required
                               class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        @error('amount')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="sm:flex-1">
                        <x-checkout.gateway-select :gateways="$enabledGateways" />
                    </div>
                    <button type="submit" class="rounded-md bg-navy-900 text-white text-sm font-medium px-5 py-2 hover:bg-navy-800 transition">Top up</button>
                </form>
                <p class="text-xs text-ink-400 mt-2">
                    Between {{ \App\Models\CustomerWallet::format($minTopup, $wallet->currency) }} and {{ \App\Models\CustomerWallet::format($maxTopup, $wallet->currency) }}.
                    Your balance goes up as soon as the payment is confirmed.
                </p>
                <x-checkout.country-switch :is-nigeria="$isNigeria" class="mt-1" />
            @endif
        </div>
    </div>

    <div class="bg-surface border border-line rounded-lg p-5 mb-8 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h2 class="font-display font-semibold text-sm text-navy-900">Use wallet for renewals</h2>
            <p class="text-xs text-ink-600 mt-1">When on, plan renewals are paid from your wallet first and your saved card covers anything left.</p>
        </div>
        <form method="POST" action="{{ route('wallet.settings') }}" class="flex items-center gap-3">
            @csrf @method('PATCH')
            <input type="hidden" name="use_for_renewals" value="{{ $wallet->use_for_renewals ? 0 : 1 }}">
            <span class="text-sm {{ $wallet->use_for_renewals ? 'text-emerald-700' : 'text-ink-400' }}">{{ $wallet->use_for_renewals ? 'On' : 'Off' }}</span>
            <button type="submit" class="rounded-md border border-line text-sm px-4 py-1.5 text-navy-900 hover:bg-surface-muted transition">
                {{ $wallet->use_for_renewals ? 'Turn off' : 'Turn on' }}
            </button>
        </form>
    </div>

    <h2 class="font-display font-semibold text-sm text-navy-900 mb-3">History</h2>

    @if ($transactions->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-6 text-center text-sm text-ink-600">
            No wallet activity yet.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                        <tr>
                            <th class="text-left px-4 py-2.5">Date</th>
                            <th class="text-left px-4 py-2.5">Type</th>
                            <th class="text-left px-4 py-2.5">Details</th>
                            <th class="text-right px-4 py-2.5">Amount</th>
                            <th class="text-right px-4 py-2.5">Balance</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($transactions as $entry)
                            <tr>
                                <td class="px-4 py-3 text-ink-600 whitespace-nowrap">{{ $entry->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-ink-900">{{ $entry->typeLabel() }}</td>
                                <td class="px-4 py-3 text-ink-600">{{ $entry->description }}@if ($entry->note) <span class="block text-xs text-ink-400">{{ $entry->note }}</span>@endif</td>
                                <td class="px-4 py-3 text-right font-mono {{ $entry->amount_minor < 0 ? 'text-red-700' : 'text-emerald-700' }}">{{ $entry->amount_minor < 0 ? '−' : '+' }}{{ \App\Models\CustomerWallet::format(abs($entry->amount_minor), $entry->currency) }}</td>
                                <td class="px-4 py-3 text-right font-mono text-ink-900">{{ \App\Models\CustomerWallet::format($entry->balance_after_minor, $entry->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="mt-4">{{ $transactions->onEachSide(1)->links() }}</div>
    @endif
@endsection
