@php
    // Audit gap #5: a mixed-currency affiliate used to only ever be offered
    // whichever currency had the largest unattached balance — this lists
    // every currency they've earned in so each can be requested on its own.
    $balancesByCurrency = $user->unpaidApprovedCommissionByCurrency();
    $minimumCents = config('referrals.minimum_payout_cents');
    $currentMethod = old('payout_method', $user->payout_method ?? 'paypal');
    $inputClass = 'w-full rounded-xl border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500';
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
                On file: <span class="font-medium text-ink-900">{{ config("referrals.payout_methods.{$user->payout_method}", $user->payout_method) }}</span>
                @if ($user->payout_method === 'paypal')
                    ({{ $user->payout_details['paypal_email'] ?? '' }})
                @else
                    ({{ $user->payout_details['bank_name'] ?? '' }})
                @endif
            </p>
        @endif
        <form method="POST" action="{{ route('referrals.payout-method') }}" class="space-y-3">
            @csrf
            <div>
                <label for="payout_method" class="block text-xs font-medium text-ink-900 mb-1">Payout method</label>
                <select id="payout_method" name="payout_method" onchange="document.getElementById('payout-fields-paypal').classList.toggle('hidden', this.value !== 'paypal'); document.getElementById('payout-fields-bank').classList.toggle('hidden', this.value !== 'bank_transfer')" class="{{ $inputClass }}">
                    @foreach (config('referrals.payout_methods') as $value => $label)
                        <option value="{{ $value }}" @selected($currentMethod === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div id="payout-fields-paypal" class="{{ $currentMethod === 'paypal' ? '' : 'hidden' }}">
                <label for="paypal_email" class="block text-xs font-medium text-ink-900 mb-1">PayPal email</label>
                <input id="paypal_email" type="email" name="paypal_email" value="{{ old('paypal_email', $user->payout_details['paypal_email'] ?? '') }}" class="{{ $inputClass }}">
            </div>
            <div id="payout-fields-bank" class="grid grid-cols-2 gap-3 {{ $currentMethod === 'bank_transfer' ? '' : 'hidden' }}">
                <div class="col-span-2">
                    <label for="bank_account_name" class="block text-xs font-medium text-ink-900 mb-1">Account holder name</label>
                    <input id="bank_account_name" type="text" name="bank_account_name" value="{{ old('bank_account_name', $user->payout_details['account_name'] ?? '') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="bank_account_number" class="block text-xs font-medium text-ink-900 mb-1">Account number</label>
                    <input id="bank_account_number" type="text" name="bank_account_number" value="{{ old('bank_account_number', $user->payout_details['account_number'] ?? '') }}" class="{{ $inputClass }}">
                </div>
                <div>
                    <label for="bank_name" class="block text-xs font-medium text-ink-900 mb-1">Bank name</label>
                    <input id="bank_name" type="text" name="bank_name" value="{{ old('bank_name', $user->payout_details['bank_name'] ?? '') }}" class="{{ $inputClass }}">
                </div>
                <div class="col-span-2">
                    <label for="bank_swift_or_routing" class="block text-xs font-medium text-ink-900 mb-1">SWIFT / routing code</label>
                    <input id="bank_swift_or_routing" type="text" name="bank_swift_or_routing" value="{{ old('bank_swift_or_routing', $user->payout_details['swift_or_routing'] ?? '') }}" class="{{ $inputClass }}">
                </div>
                <div class="col-span-2">
                    <label for="bank_code" class="block text-xs font-medium text-ink-900 mb-1">Flutterwave bank code (Nigeria only, optional)</label>
                    <input id="bank_code" type="text" name="bank_code" value="{{ old('bank_code', $user->payout_details['bank_code'] ?? '') }}" placeholder="e.g. 044" class="{{ $inputClass }}">
                    <p class="text-xs text-ink-400 mt-1">Only needed for instant payouts from our payout wallet — find your bank's code on Flutterwave's published bank list. Without it, payouts are still sent, just processed manually.</p>
                </div>
            </div>
            <x-ui.button class="w-full">Save payout details</x-ui.button>
        </form>
    </x-ui.card>

    <x-ui.card title="Request a payout" :description="'Minimum $'.number_format($minimumCents / 100).' in approved commission.'" icon="banknotes">
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
                        @elseif ($cents < $minimumCents)
                            <span class="text-xs text-ink-500 whitespace-nowrap">{{ $currency }} {{ number_format(($minimumCents - $cents) / 100, 2) }} short of minimum</span>
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
