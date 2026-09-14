@extends('layouts.marketing')

@section('title', 'Plans & pricing · '.(\App\Models\SiteSetting::get('site_name', 'AffilStack')))

@section('content')
    <div class="max-w-5xl mx-auto px-6 py-14">
        <div class="text-center mb-10">
            <h1 class="font-display font-semibold text-3xl text-navy-900">Plans &amp; pricing</h1>
            <p class="text-ink-600 mt-2">Pick a plan to create your account — payment happens once, your account is ready the moment it clears.</p>
        </div>

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
                    <a href="{{ route('registration.form', $plan) }}" class="w-full text-center rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">
                        Get started
                    </a>
                </div>
            @endforeach
        </div>

        <p class="text-xs text-ink-400 mt-6 text-center">Payments are processed securely by Stripe or Flutterwave. Every plan comes with a 14-day money-back guarantee — email support if it's not for you.</p>
    </div>
@endsection
