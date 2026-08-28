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
                    <button class="w-full rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">
                        {{ $subscription?->plan_id === $plan->id ? 'Current plan' : 'Choose plan' }}
                    </button>
                </form>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-ink-400 mt-6">Payments are processed securely by Flutterwave. You'll be redirected to their checkout to complete payment.</p>
@endsection
