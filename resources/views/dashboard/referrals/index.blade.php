@extends('layouts.app')

@section('title', 'Referrals')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-5">Share your link — you earn {{ number_format(config('referrals.commission_rate') * 100) }}% of what everyone you refer pays, for their first payment and every renewal after it.</p>

    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Your referral link</div>
        <div class="flex flex-wrap items-center gap-3">
            <code class="text-sm bg-surface-muted rounded px-3 py-1.5">{{ $user->referral_link }}</code>
            <button type="button" onclick="navigator.clipboard.writeText('{{ $user->referral_link }}')" class="text-xs text-brand-600 hover:text-brand-700 font-medium">Copy</button>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-4 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Link clicks</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ number_format($clickCount) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Pending</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['pending_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Approved</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['approved_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Paid out</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['paid_cents'] / 100, 2) }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-6">
            <h2 class="text-sm font-semibold text-navy-900 mb-3">Payout details</h2>
            @if ($user->hasPayoutMethodOnFile())
                <p class="text-sm text-ink-600 mb-3">
                    On file: <span class="font-medium text-ink-900">{{ config("referrals.payout_methods.{$user->payout_method}", $user->payout_method) }}</span>
                    @if ($user->payout_method === 'paypal')
                        ({{ $user->payout_details['paypal_email'] ?? '' }})
                    @else
                        ({{ $user->payout_details['bank_name'] ?? '' }})
                    @endif
                </p>
            @endif
            @php $currentMethod = old('payout_method', $user->payout_method ?? 'paypal'); @endphp
            <form method="POST" action="{{ route('referrals.payout-method') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-ink-900 mb-1">Payout method</label>
                    <select name="payout_method" onchange="document.getElementById('payout-fields-paypal').classList.toggle('hidden', this.value !== 'paypal'); document.getElementById('payout-fields-bank').classList.toggle('hidden', this.value !== 'bank_transfer')" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                        @foreach (config('referrals.payout_methods') as $value => $label)
                            <option value="{{ $value }}" @selected($currentMethod === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div id="payout-fields-paypal" class="{{ $currentMethod === 'paypal' ? '' : 'hidden' }}">
                    <label class="block text-xs font-medium text-ink-900 mb-1">PayPal email</label>
                    <input type="email" name="paypal_email" value="{{ old('paypal_email', $user->payout_details['paypal_email'] ?? '') }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                </div>
                <div id="payout-fields-bank" class="grid grid-cols-2 gap-3 {{ $currentMethod === 'bank_transfer' ? '' : 'hidden' }}">
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-ink-900 mb-1">Account holder name</label>
                        <input type="text" name="bank_account_name" value="{{ old('bank_account_name', $user->payout_details['account_name'] ?? '') }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Account number</label>
                        <input type="text" name="bank_account_number" value="{{ old('bank_account_number', $user->payout_details['account_number'] ?? '') }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Bank name</label>
                        <input type="text" name="bank_name" value="{{ old('bank_name', $user->payout_details['bank_name'] ?? '') }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                    </div>
                    <div class="col-span-2">
                        <label class="block text-xs font-medium text-ink-900 mb-1">SWIFT / routing code</label>
                        <input type="text" name="bank_swift_or_routing" value="{{ old('bank_swift_or_routing', $user->payout_details['swift_or_routing'] ?? '') }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                    </div>
                </div>
                <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2 hover:bg-navy-800 transition">Save payout details</button>
            </form>
        </div>

        <div class="bg-surface border border-line rounded-lg p-6">
            <h2 class="text-sm font-semibold text-navy-900 mb-3">Request a payout</h2>
            @php
                $unpaidCents = $user->unpaidApprovedCommissionCents();
                $minimumCents = config('referrals.minimum_payout_cents');
            @endphp
            <p class="text-sm text-ink-600 mb-4">You have <span class="font-medium text-ink-900">${{ number_format($unpaidCents / 100, 2) }}</span> in approved commissions available to request.</p>
            @if ($user->hasOpenPayoutRequest())
                <p class="text-sm text-amber-700 bg-amber-50 border border-amber-200 rounded-md px-3 py-2">A payout request is already being processed — you'll be notified once it's paid.</p>
            @elseif (! $user->hasPayoutMethodOnFile())
                <p class="text-sm text-ink-500">Add your payout details before requesting a payout.</p>
            @elseif ($unpaidCents < $minimumCents)
                <p class="text-sm text-ink-500">You need at least ${{ number_format($minimumCents / 100, 2) }} in approved commissions to request a payout — you're ${{ number_format(($minimumCents - $unpaidCents) / 100, 2) }} away.</p>
            @else
                <form method="POST" action="{{ route('referrals.payout') }}">
                    @csrf
                    <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2 hover:bg-navy-800 transition">Request payout of ${{ number_format($unpaidCents / 100, 2) }}</button>
                </form>
            @endif
        </div>
    </div>

    @if ($payouts->isNotEmpty())
        <h2 class="text-sm font-semibold text-navy-900 mb-3">Payout history</h2>
        <div class="bg-surface border border-line rounded-lg overflow-hidden mb-8">
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
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($payout->amount_cents / 100, 2) }} {{ $payout->currency }}</td>
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
    @endif

    <h2 class="text-sm font-semibold text-navy-900 mb-3">People you've referred</h2>

    @if ($referrals->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No one has signed up through your link yet. Share it — every signup and every renewal they make earns you a commission.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
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
                                {{ $referral->referredUser->name }}
                                <div class="text-xs text-ink-500">{{ $referral->referredUser->email }}</div>
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
@endsection
