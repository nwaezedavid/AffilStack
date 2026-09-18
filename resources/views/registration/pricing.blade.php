@extends('layouts.marketing')

@section('title', 'Plans & pricing · '.(\App\Models\SiteSetting::get('site_name', 'AffilStack')))

@section('content')
    <div class="max-w-6xl mx-auto px-6 py-14">
        <div class="text-center mb-10">
            <h1 class="font-display font-semibold text-3xl text-navy-900">Plans &amp; pricing</h1>
            <p class="text-ink-600 mt-2">Pick a plan to create your account — payment happens once, your account is ready the moment it clears.</p>
        </div>

        <div class="grid sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4 items-start">
            @foreach ($plans as $plan)
                <div class="bg-surface border rounded-lg p-5 flex flex-col gap-3 {{ $plan->is_featured ? 'border-gold-500 ring-1 ring-gold-500' : 'border-line' }}">
                    @if ($plan->is_featured)
                        <span class="text-xs font-mono uppercase text-gold-600">Most popular</span>
                    @endif
                    <div class="font-display font-semibold text-base text-navy-900">{{ $plan->name }}</div>
                    <div class="font-mono text-2xl font-semibold text-ink-900">${{ number_format($plan->priceMonthly()) }}<span class="text-xs font-sans text-ink-400">/mo</span></div>
                    <p class="text-xs text-ink-600">{{ $plan->description }}</p>

                    <ul class="text-xs text-ink-900 space-y-1 flex-1">
                        <li class="flex items-start gap-1.5"><span class="text-emerald-600" aria-hidden="true">✓</span> {{ number_format($plan->credits_per_month) }} AI credits / month</li>
                        <li class="flex items-start gap-1.5"><span class="text-emerald-600" aria-hidden="true">✓</span> {{ $plan->contact_limit === 0 ? 'Unlimited' : number_format($plan->contact_limit) }} CRM contacts</li>
                        <li class="flex items-start gap-1.5">
                            <span class="text-emerald-600" aria-hidden="true">✓</span>
                            {{ $plan->team_seats }} team seat{{ $plan->team_seats > 1 ? 's' : '' }}
                            @if ($plan->isSharedTeamPlan())
                                — shared across your whole offer list
                            @endif
                        </li>
                    </ul>

                    <a href="{{ route('registration.form', $plan) }}" class="w-full text-center rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">
                        Get started
                    </a>

                    @if (! empty($plan->channels) || $plan->active_products_limit)
                        <details class="group -mx-1">
                            <summary class="cursor-pointer list-none flex items-center justify-center gap-1 text-xs font-medium text-brand-600 hover:text-brand-700 px-1 py-1 select-none">
                                <span>See everything included</span>
                                <span class="text-ink-400 group-open:rotate-45 transition" aria-hidden="true">+</span>
                            </summary>

                            <div class="mt-3 pt-3 border-t border-line text-xs text-ink-600 space-y-3">
                                <ul class="space-y-1">
                                    <li class="flex items-start gap-1.5"><span class="text-emerald-600" aria-hidden="true">✓</span> {{ $plan->active_products_limit === 0 ? 'Unlimited active offers' : $plan->active_products_limit.' active offer'.($plan->active_products_limit > 1 ? 's' : '') }}</li>
                                    <li class="flex items-start gap-1.5"><span class="text-emerald-600" aria-hidden="true">✓</span> Built-in referral / affiliate program</li>
                                    <li class="flex items-start gap-1.5"><span class="text-emerald-600" aria-hidden="true">✓</span> Email support</li>
                                </ul>

                                @if (! empty($plan->channels))
                                    <div>
                                        <p class="font-medium text-navy-900 mb-1">Channels &amp; content types</p>
                                        <ul class="space-y-1">
                                            @foreach ($plan->channels as $channel)
                                                <li class="flex items-start gap-1.5">
                                                    <span class="text-emerald-600" aria-hidden="true">✓</span>
                                                    {{ \App\Models\Plan::channelLabels()[$channel] ?? \Illuminate\Support\Str::headline($channel) }}
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            </div>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>

        <p class="text-xs text-ink-400 mt-6 text-center">Payments are processed securely — the payment methods available depend on your country. See our <a href="{{ route('refund-policy') }}" class="underline hover:text-ink-600">refund policy</a> before you buy.</p>
    </div>
@endsection
