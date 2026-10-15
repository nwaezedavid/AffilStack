@extends('layouts.partner')

@section('title', 'Earnings & payouts')

@php
    use App\Services\Referrals\PayoutMethodService;
    use App\Support\ReferralProgram;

    $maskEmail = function (?string $email): string {
        if (! $email || ! str_contains($email, '@')) {
            return '';
        }
        [$local, $domain] = explode('@', $email, 2);

        return mb_substr($local, 0, 1).str_repeat('•', max(2, min(6, mb_strlen($local) - 1))).'@'.$domain;
    };
    $statusStyles = ['paid' => 'bg-emerald-100 text-emerald-800', 'rejected' => 'bg-rose-100 text-rose-800', 'disbursing' => 'bg-sky-100 text-sky-800', 'requested' => 'bg-amber-100 text-amber-800'];
    $statusLabels = ['paid' => 'Paid', 'rejected' => 'Not paid', 'disbursing' => 'Sending', 'requested' => 'Awaiting approval'];
@endphp

@section('content')
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
        <x-ui.stat label="Pending review" :value="ReferralProgram::money($balances['pending'])" icon="clock" tone="violet" hint="Checked before approval" />
        <x-ui.stat label="Approved, ready to request" :value="ReferralProgram::money($balances['approved'])" icon="check-badge" tone="emerald" hint="Request a payout below" />
        <x-ui.stat label="Paid to you" :value="ReferralProgram::money($balances['paid'])" icon="banknotes" tone="gold" hint="All time" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <x-ui.card title="Payout details" description="Where we send your commission." icon="building-library">
            @if ($user->hasPayoutMethodOnFile())
                <p class="mb-4 rounded-xl bg-emerald-50 px-3.5 py-2.5 text-sm text-emerald-900">
                    On file: <span class="font-medium">{{ PayoutMethodService::summary($user->payout_method, $user->payout_details) }}</span>
                </p>
            @endif
            @include('partials.referrals.payout-form', ['action' => route('partner.payouts.method'), 'resolveUrl' => route('partner.payouts.resolve')])
        </x-ui.card>

        <x-ui.card title="Request a payout" :description="'Minimum '.ReferralProgram::money(ReferralProgram::minimumPayoutUsdCents()).' (or the naira equivalent) in approved commission. We review every request before sending.'" icon="paper-airplane">
            @if ($balancesByCurrency->isEmpty())
                <p class="text-sm text-ink-600">No approved commission to pay out yet. New commissions are reviewed first, then move to "approved".</p>
            @elseif (! $user->hasPayoutMethodOnFile())
                <p class="text-sm text-ink-600">Add your payout details first.</p>
            @else
                <ul class="divide-y divide-line">
                    @foreach ($balancesByCurrency as $currency => $cents)
                        @php
                            $minimum = ReferralProgram::minimumPayoutCents($currency);
                        @endphp
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div>
                                <p class="font-display text-xl font-semibold tabular-nums text-navy-900">{{ ReferralProgram::money($cents, $currency) }}</p>
                                <p class="text-xs text-ink-400">Approved in {{ $currency }}</p>
                            </div>
                            @if ($user->hasOpenPayoutRequest($currency))
                                <span class="rounded-full bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-800 ring-1 ring-amber-200">Request in progress</span>
                            @elseif ($cents < $minimum)
                                <span class="text-xs text-ink-400">{{ ReferralProgram::money($minimum - $cents, $currency) }} to go</span>
                            @else
                                <form method="POST" action="{{ route('partner.payouts.request') }}">
                                    @csrf
                                    <input type="hidden" name="currency" value="{{ $currency }}">
                                    <button type="submit" class="rounded-full bg-emerald-900 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800">Request {{ ReferralProgram::money($cents, $currency) }}</button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            <div class="mt-5 rounded-xl bg-surface-muted p-4 text-xs text-ink-600">
                <p class="font-medium text-ink-900">How payouts work</p>
                <ul class="mt-1.5 list-disc space-y-1 pl-4">
                    <li>Nigerian bank: sent in naira by Paystack. Dollar earnings are converted at the rate on the day we send.</li>
                    <li>PayPal: sent to your PayPal email.</li>
                    <li>USDT: sent to your wallet; you'll see the transaction hash here once it's on-chain.</li>
                </ul>
            </div>
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6" title="Payout history" icon="clock" padding="p-0">
        @if ($payouts->isEmpty())
            <p class="px-5 py-6 text-sm text-ink-600 sm:px-6">Your payouts will be listed here.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted font-mono text-xs uppercase tracking-wide text-ink-400">
                        <tr>
                            <th class="px-5 py-2.5 text-left sm:px-6">Requested</th>
                            <th class="px-4 py-2.5 text-right">Amount</th>
                            <th class="px-4 py-2.5 text-left">Sent to</th>
                            <th class="px-4 py-2.5 text-left">Status</th>
                            <th class="px-5 py-2.5 text-left sm:px-6">Reference</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($payouts as $payout)
                            @php
                                $explorer = $payout->payout_method === 'usdt' && $payout->reference
                                    ? config('referrals.usdt_networks.'.($payout->payout_details['network'] ?? '').'.explorer')
                                    : null;
                            @endphp
                            <tr class="align-top">
                                <td class="px-5 py-3 text-ink-600 sm:px-6">{{ $payout->requested_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums text-navy-900">
                                    {{ ReferralProgram::money($payout->amount_cents, $payout->currency) }}
                                    @if ($payout->sent_amount_cents && $payout->sent_currency !== $payout->currency)
                                        <span class="block text-xs text-ink-400">{{ ReferralProgram::money($payout->sent_amount_cents, $payout->sent_currency) }} sent</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-ink-600">{{ PayoutMethodService::summary($payout->payout_method, $payout->payout_details) }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $statusStyles[$payout->status] ?? 'bg-surface-muted text-ink-600' }}">{{ $statusLabels[$payout->status] ?? ucfirst($payout->status) }}</span>
                                    @if ($payout->status === 'rejected' && $payout->note)
                                        <span class="mt-1 block max-w-xs text-xs text-ink-400">{{ $payout->note }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 font-mono text-xs text-ink-600 sm:px-6">
                                    @if ($explorer)
                                        <a href="{{ $explorer.$payout->reference }}" target="_blank" rel="noopener" class="text-emerald-700 underline">{{ PayoutMethodService::shortAddress($payout->reference) }}</a>
                                    @elseif ($payout->status === 'paid')
                                        {{ $payout->reference }}
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    <x-ui.card class="mt-6" title="People you've referred" description="Emails are partly hidden to protect your customers' privacy." icon="user-group" padding="p-0">
        @if ($referrals->isEmpty())
            <p class="px-5 py-6 text-sm text-ink-600 sm:px-6">No one has signed up through your links yet. Every sign-up and every renewal they pay earns you commission.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted font-mono text-xs uppercase tracking-wide text-ink-400">
                        <tr>
                            <th class="px-5 py-2.5 text-left sm:px-6">Customer</th>
                            <th class="px-4 py-2.5 text-left">Campaign</th>
                            <th class="px-4 py-2.5 text-left">Status</th>
                            <th class="px-4 py-2.5 text-left">Signed up</th>
                            <th class="px-4 py-2.5 text-right">Payments</th>
                            <th class="px-5 py-2.5 text-right sm:px-6">Earned</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($referrals as $referral)
                            @php
                            $earned = $referral->events->whereNotIn('status', ['reversed', 'rejected']);
                        @endphp
                            <tr>
                                <td class="px-5 py-3 sm:px-6">
                                    <span class="text-navy-900">{{ \Illuminate\Support\Str::of($referral->referredUser?->name ?? 'Deleted account')->before(' ') }}</span>
                                    <span class="block text-xs text-ink-400">{{ $maskEmail($referral->referredUser?->email) }}</span>
                                </td>
                                <td class="px-4 py-3 text-ink-600">{{ $referral->campaign?->name ?? '—' }}</td>
                                <td class="px-4 py-3">
                                    <span class="rounded-full px-2 py-0.5 text-xs {{ $referral->status === 'converted' ? 'bg-emerald-100 text-emerald-800' : 'bg-surface-muted text-ink-600' }}">{{ $referral->status === 'converted' ? 'Paying' : 'Signed up' }}</span>
                                </td>
                                <td class="px-4 py-3 text-ink-600">{{ $referral->created_at->format('M j, Y') }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ $earned->where('amount_cents', '>', 0)->count() }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-navy-900 sm:px-6">{{ ReferralProgram::money((int) $earned->sum(fn ($e) => ReferralProgram::toUsdCents((int) $e->amount_cents, (string) $e->currency))) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@endsection
