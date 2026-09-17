@extends('layouts.app')

@section('title', 'Billing & Plan')

@section('content')
    @if ($subscription)
        <div class="bg-surface border border-line rounded-lg p-5 mb-8 flex items-center justify-between">
            <div>
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Current plan</div>
                <div class="font-display font-semibold text-lg text-navy-900">{{ $subscription->plan->name }} · {{ ucfirst($subscription->billing_cycle) }}</div>
            </div>
            <div class="text-sm text-ink-600 text-right">
                Renews {{ $subscription->current_period_end?->format('M j, Y') }}
            </div>
        </div>
    @endif

    <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
        @foreach ($plans as $plan)
            <div class="bg-surface border rounded-lg p-5 flex flex-col gap-3 {{ $plan->is_featured ? 'border-gold-500 ring-1 ring-gold-500' : 'border-line' }}">
                @if ($plan->is_featured)
                    <span class="text-xs font-mono uppercase text-gold-600">Most popular</span>
                @endif
                <div class="font-display font-semibold text-base text-navy-900">{{ $plan->name }}</div>
                <div class="font-mono text-2xl font-semibold text-ink-900">${{ number_format($plan->priceMonthly()) }}<span class="text-xs font-sans text-ink-400">/mo</span></div>
                <p class="text-xs text-ink-600">{{ $plan->description }}</p>
                <ul class="text-xs text-ink-900 space-y-1 flex-1">
                    <li>{{ number_format($plan->credits_per_month) }} AI credits / month</li>
                    <li>{{ $plan->contact_limit === 0 ? 'Unlimited' : number_format($plan->contact_limit) }} CRM contacts</li>
                    <li>{{ $plan->team_seats }} team seat{{ $plan->team_seats > 1 ? 's' : '' }}</li>
                </ul>
                <form method="POST" action="{{ route('billing.checkout', $plan) }}" class="space-y-2">
                    @csrf
                    <select name="billing_cycle" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                        <option value="monthly">Monthly — ${{ number_format($plan->priceMonthly()) }}/mo</option>
                        <option value="yearly">Yearly — ${{ number_format($plan->priceYearly()) }}/yr</option>
                    </select>
                    @if (count($enabledGateways) > 1)
                        <select name="gateway" class="w-full rounded-md border border-line px-2 py-1.5 text-xs">
                            @foreach ($enabledGateways as $gateway)
                                <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                            @endforeach
                        </select>
                    @elseif (count($enabledGateways) === 1)
                        <input type="hidden" name="gateway" value="{{ $enabledGateways[0]->key() }}">
                    @endif
                    <button class="w-full rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">
                        {{ $subscription?->plan_id === $plan->id ? 'Current plan' : 'Choose plan' }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-ink-400 mt-6">Payments are processed securely by {{ $enabledGateways ? collect($enabledGateways)->map->label()->implode(' or ') : 'our payment provider' }}. You'll be redirected to complete payment.</p>

    <h2 class="font-display font-semibold text-sm text-navy-900 mt-10 mb-3">Billing history</h2>

    @if ($transactions->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-6 text-center text-sm text-ink-600">
            No payments recorded yet.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Date</th>
                        <th class="text-left px-4 py-2.5">Description</th>
                        <th class="text-left px-4 py-2.5">Gateway</th>
                        <th class="text-right px-4 py-2.5">Amount</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($transactions as $transaction)
                        <tr>
                            <td class="px-4 py-3 text-ink-600">{{ ($transaction->processed_at ?? $transaction->created_at)->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-ink-900 capitalize">{{ str_replace('_', ' ', $transaction->type) }}</td>
                            <td class="px-4 py-3 text-ink-600 capitalize">{{ $transaction->gateway }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ strtoupper($transaction->currency) }} {{ number_format($transaction->amount_cents / 100, 2) }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'text-xs font-mono px-2 py-1 rounded capitalize',
                                    'bg-emerald-50 text-emerald-700' => $transaction->status === 'successful',
                                    'bg-red-50 text-red-700' => $transaction->status === 'failed',
                                    'bg-amber-50 text-amber-700' => in_array($transaction->status, ['refunded', 'charged_back'], true),
                                    'bg-surface-muted text-ink-600' => ! in_array($transaction->status, ['successful', 'failed', 'refunded', 'charged_back'], true),
                                ])>{{ str_replace('_', ' ', $transaction->status) }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $transactions->onEachSide(1)->links() }}</div>
    @endif
@endsection
