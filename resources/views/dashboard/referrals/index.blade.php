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
