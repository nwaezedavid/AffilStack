@php
    // Audit gap #5: a mixed-currency affiliate used to only ever be offered
    // whichever currency had the largest unattached balance — this lists
    // every currency they've earned in so each can be requested on its own.
    $balancesByCurrency = $user->unpaidApprovedCommissionByCurrency();
    $minimumCents = \App\Support\ReferralProgram::minimumPayoutUsdCents();
@endphp

<div class="grid grid-cols-2 gap-4 lg:grid-cols-4 mb-6">
    <x-ui.stat label="Link clicks" :value="number_format($clickCount)" icon="cursor-arrow-rays" />
    <x-ui.stat label="Pending review" :value="'$'.number_format($totals['pending_cents'] / 100, 2)" icon="clock" tone="violet" />
    <x-ui.stat label="Approved" :value="'$'.number_format($totals['approved_cents'] / 100, 2)" icon="check-badge" tone="emerald" />
    <x-ui.stat label="Paid out" :value="'$'.number_format($totals['paid_cents'] / 100, 2)" icon="banknotes" tone="gold" />
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
    <x-ui.card title="Payout details" description="Where we send your commission." icon="building-library">
        @if ($user->hasPayoutMethodOnFile())
            <p class="text-sm text-ink-600 mb-4 rounded-xl bg-emerald-50 px-3 py-2">
                On file: <span class="font-medium text-ink-900">{{ \App\Services\Referrals\PayoutMethodService::summary($user->payout_method, $user->payout_details) }}</span>
            </p>
        @endif
        @include('partials.referrals.payout-form', ['action' => route('referrals.payout-method'), 'resolveUrl' => route('referrals.payout-resolve')])
    </x-ui.card>

    <x-ui.card title="Request a payout" :description="'Minimum '.\App\Support\ReferralProgram::money($minimumCents).' in approved commission.'" icon="banknotes">
        @if ($balancesByCurrency->isEmpty())
            <p class="text-sm text-ink-500">You don't have any approved commissions to pay out yet.</p>
        @elseif (! $user->hasPayoutMethodOnFile())
            <p class="text-sm text-ink-500">Add your payout details before requesting a payout.</p>
        @else
            <div class="space-y-3">
                @foreach ($balancesByCurrency as $currency => $cents)
                    <div class="flex items-center justify-between gap-3 {{ ! $loop->last ? 'pb-3 border-b border-line' : '' }}">
                        <p class="text-sm text-ink-600">You have <span class="font-medium text-ink-900">{{ $currency }} {{ number_format($cents / 100, 2) }}</span> in approved commissions.</p>
                        @if ($user->hasOpenPayoutRequest($currency))
                            <span class="text-xs text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-2.5 py-1.5 whitespace-nowrap">Request processing</span>
                        @elseif ($cents < \App\Support\ReferralProgram::minimumPayoutCents($currency))
                            <span class="text-xs text-ink-500 whitespace-nowrap">{{ \App\Support\ReferralProgram::money(\App\Support\ReferralProgram::minimumPayoutCents($currency) - $cents, $currency) }} short of minimum</span>
                        @else
                            <form method="POST" action="{{ route('referrals.payout') }}">
                                @csrf
                                <input type="hidden" name="currency" value="{{ $currency }}">
                                <button type="submit" class="rounded-full bg-navy-900 text-white text-xs font-medium px-3 py-1.5 hover:bg-navy-800 transition whitespace-nowrap">Request {{ $currency }} {{ number_format($cents / 100, 2) }}</button>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.card>
</div>

@if ($payouts->isNotEmpty())
    <x-ui.card class="mb-6" title="Payout history" icon="clock" padding="p-0">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Requested</th>
                        <th class="text-right px-4 py-2.5">Amount</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                        <th class="text-left px-4 py-2.5">Processed</th>
                        <th class="text-left px-4 py-2.5">Reference</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($payouts as $payout)
                        <tr>
                            <td class="px-4 py-3 text-ink-600">{{ $payout->requested_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ \App\Support\ReferralProgram::money($payout->amount_cents, $payout->currency) }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-0.5 {{ match($payout->status) { 'paid' => 'bg-green-100 text-green-800', 'rejected' => 'bg-red-100 text-red-800', default => 'bg-surface-muted text-ink-600' } }}">
                                    {{ ucfirst($payout->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-600">{{ $payout->processed_at?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-600">{{ $payout->reference ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-ui.card>
@endif

<x-ui.card title="People you've referred" icon="user-group" padding="p-0">
    @if ($referrals->isEmpty())
        <div class="p-8 text-center text-sm text-ink-600">
            No one has signed up through your link yet. Share it — every signup and every renewal they make earns you a commission.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Referred user</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                        <th class="text-left px-4 py-2.5">Signed up</th>
                        <th class="text-right px-4 py-2.5">Payments</th>
                        <th class="text-right px-4 py-2.5">Earned</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($referrals as $referral)
                        <tr>
                            <td class="px-4 py-3 text-ink-900">
                                {{ $referral->referredUser?->name ?? 'Deleted account' }}
                                <div class="text-xs text-ink-500">{{ $referral->referredUser?->email }}</div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-0.5 {{ $referral->status === 'converted' ? 'bg-green-100 text-green-800' : 'bg-surface-muted text-ink-600' }}">
                                    {{ $referral->status === 'converted' ? 'Converted' : 'Signed up' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-600">{{ $referral->created_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $referral->events->count() }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($referral->events->sum('amount_cents') / 100, 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>
