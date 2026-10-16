@extends('layouts.developer')

@use('App\Services\Referrals\PayoutMethodService')
@use('App\Support\ReferralProgram')

@section('title', 'Earnings')
@section('noindex', '1')

@php
    $minimum = ReferralProgram::minimumPayoutCents('USD');
    $typeLabels = ['sale' => 'Subscription payment', 'refund' => 'Refund', 'chargeback' => 'Chargeback', 'adjustment' => 'Adjustment'];
    $statusLabels = ['pending' => 'In refund window', 'approved' => 'Available', 'paid' => 'Paid', 'rejected' => 'Rejected', 'reversed' => 'Reversed'];
@endphp

@section('content')
    <h1 class="font-display text-2xl font-semibold text-navy-900">Earnings</h1>
    <p class="mt-1 text-sm text-ink-600">You get {{ rtrim(rtrim(number_format($developer->sharePercent(), 2), '0'), '.') }}% of every payment for your add-ons, in US dollars. Each payment's share can be paid out {{ \App\Support\Marketplace\MarketplaceSettings::holdDays() }} days later, once the refund window has passed.</p>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.stat label="Available to pay out" :value="ReferralProgram::money($balance['available'], 'USD')" icon="banknotes" tone="emerald" />
        <x-ui.stat label="In the refund window" :value="ReferralProgram::money($balance['pending'], 'USD')" icon="clock" tone="gold" />
        <x-ui.stat label="Paid to you" :value="ReferralProgram::money($balance['paid'], 'USD')" icon="check-badge" tone="brand" hint="All time" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Payout details" description="Where we send your earnings. The same details are used for referral commission on your account." icon="building-library">
            @if ($user->hasPayoutMethodOnFile())
                <p class="mb-4 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-900">On file: <span class="font-medium">{{ PayoutMethodService::summary($user->payout_method, $user->payout_details) }}</span></p>
            @endif
            @include('partials.referrals.payout-form', ['action' => route('developer.payout-method'), 'resolveUrl' => route('developer.payout-resolve')])
        </x-ui.card>

        <x-ui.card title="Request a payout" :description="'Minimum '.ReferralProgram::money($minimum, 'USD').'. We review every request before sending.'" icon="paper-airplane">
            <p class="font-display text-3xl font-semibold tabular-nums text-navy-900">{{ ReferralProgram::money($balance['available'], 'USD') }}</p>
            <p class="text-xs text-ink-400">Available now</p>
            <div class="mt-4">
                @if ($user->hasOpenPayoutRequest('USD', 'developer'))
                    <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">Request in progress</span>
                @elseif (! $user->hasPayoutMethodOnFile())
                    <p class="text-sm text-ink-600">Add your payout details first.</p>
                @elseif ($balance['available'] < $minimum)
                    <p class="text-sm text-ink-600">{{ ReferralProgram::money(max(0, $minimum - $balance['available']), 'USD') }} to go before you can request a payout.</p>
                @else
                    <form method="POST" action="{{ route('developer.payout.request') }}">
                        @csrf
                        <x-ui.button variant="gold" icon="paper-airplane">Request {{ ReferralProgram::money($balance['available'], 'USD') }}</x-ui.button>
                    </form>
                @endif
            </div>
            <p class="mt-5 rounded-xl bg-surface-muted p-4 text-xs text-ink-600">Nigerian bank payouts are sent in naira at the day's rate. Refunds and chargebacks are taken back from your earnings.</p>
        </x-ui.card>
    </div>

    @if ($payouts->isNotEmpty())
        <x-ui.card class="mt-6" title="Payouts" icon="arrow-up-right" padding="p-0">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-xs text-ink-400"><tr><th class="px-5 py-2.5 font-medium">Requested</th><th class="px-4 py-2.5 font-medium">Amount</th><th class="px-4 py-2.5 font-medium">Method</th><th class="px-4 py-2.5 font-medium">Status</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($payouts as $payout)
                            <tr>
                                <td class="px-5 py-3 text-ink-600">{{ $payout->requested_at?->format('M j, Y') }}</td>
                                <td class="px-4 py-3 tabular-nums text-navy-900">{{ ReferralProgram::money($payout->amount_cents, $payout->currency) }}</td>
                                <td class="px-4 py-3 text-ink-600">{{ PayoutMethodService::summary($payout->payout_method, $payout->payout_details) }}</td>
                                <td class="px-4 py-3">{{ ucfirst($payout->status) }}@if ($payout->status === 'rejected' && $payout->note)<span class="block text-xs text-ink-400">{{ $payout->note }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif

    <x-ui.card class="mt-6" title="Earnings history" icon="chart-bar" padding="p-0">
        @if ($earnings->isEmpty())
            <p class="px-5 py-6 text-sm text-ink-600">No earnings yet. They appear here when customers pay for your add-ons.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-muted text-xs text-ink-400"><tr><th class="px-5 py-2.5 font-medium">Date</th><th class="px-4 py-2.5 font-medium">Add-on</th><th class="px-4 py-2.5 font-medium">What</th><th class="px-4 py-2.5 text-right font-medium">Payment</th><th class="px-4 py-2.5 text-right font-medium">Your share</th><th class="px-4 py-2.5 font-medium">Status</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($earnings as $earning)
                            <tr>
                                <td class="px-5 py-3 text-ink-600">{{ $earning->occurred_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-navy-900">{{ $earning->addon?->liveVersion?->name ?? $earning->addon?->slug }}</td>
                                <td class="px-4 py-3 text-ink-600">{{ $typeLabels[$earning->type] ?? $earning->type }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-ink-600">{{ ReferralProgram::money($earning->gross_cents, $earning->currency) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums font-medium {{ $earning->amount_cents < 0 ? 'text-rose-700' : 'text-navy-900' }}">{{ ReferralProgram::money($earning->amount_cents, $earning->currency) }}</td>
                                <td class="px-4 py-3 text-xs text-ink-600">{{ $statusLabels[$earning->status] ?? $earning->status }}@if ($earning->status === 'pending' && $earning->available_at)<span class="block text-ink-400">from {{ $earning->available_at->format('M j') }}</span>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="px-5 py-4">{{ $earnings->links() }}</div>
        @endif
    </x-ui.card>
@endsection
