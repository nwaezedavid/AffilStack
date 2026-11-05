@extends('layouts.marketing')
@use('App\Support\Website\SiteCopy')

{{-- /pricing — plans come from Billing → Plans; every other word is edited
     in Website → Pricing page. --}}
@section('title', 'Plans & pricing · '.(\App\Models\SiteSetting::get('site_name', 'AffilStack')))

@include('marketing.partials.motion')

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="pricing-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(60% 80% at 50% 0%, rgba(36,82,217,.55) 0%, transparent 70%), radial-gradient(40% 60% at 100% 100%, rgba(201,162,74,.35) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-3xl px-4 pt-16 pb-36 text-center sm:px-6 lg:pt-20">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">{{ SiteCopy::text('pricing_eyebrow') }}</p>
            <h1 id="pricing-heading" class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ SiteCopy::highlight('pricing_heading', 'text-gold-300') }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-base text-white/75 sm:text-lg">{{ SiteCopy::text('pricing_subheading') }}</p>
            @if ($guarantee = SiteCopy::text('pricing_guarantee'))
                <p class="mt-5 inline-flex items-center gap-2 text-sm text-white/70"><x-heroicon-s-shield-check class="h-4 w-4 text-emerald-400" aria-hidden="true" /> {{ $guarantee }}</p>
            @endif
        </div>
    </section>

    <section class="relative -mt-24 pb-16">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 items-start gap-5 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                @foreach ($plans as $plan)
                    <div class="relative flex flex-col gap-3 rounded-3xl p-6 shadow-[0_24px_48px_-28px_rgba(11,30,61,.45)] {{ $plan->is_featured ? 'bg-navy-900 text-white ring-2 ring-gold-400' : 'bg-white ring-1 ring-line' }}" data-reveal>
                        @if ($plan->is_featured)
                            <span class="absolute -top-3 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full bg-gold-400 px-3 py-1 text-xs font-semibold text-navy-950 shadow">{{ SiteCopy::text('pricing_featured_label') }}</span>
                        @endif
                        <div class="font-display text-lg font-semibold {{ $plan->is_featured ? 'text-white' : 'text-navy-900' }}">{{ $plan->name }}</div>
                        <div class="font-display text-4xl font-semibold tracking-tight">${{ number_format($plan->priceMonthly()) }}<span class="ml-1 font-sans text-sm font-normal {{ $plan->is_featured ? 'text-white/60' : 'text-ink-400' }}">/mo</span></div>
                        <p class="text-sm {{ $plan->is_featured ? 'text-white/70' : 'text-ink-600' }}">{{ $plan->description }}</p>

                        <ul class="flex-1 space-y-2 text-sm" role="list">
                            <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> {{ number_format($plan->credits_per_month) }} AI credits / month</li>
                            <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> {{ $plan->contact_limit === 0 ? 'Unlimited' : number_format($plan->contact_limit) }} CRM contacts</li>
                            <li class="flex items-start gap-2">
                                <x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" />
                                <span>{{ $plan->team_seats }} team seat{{ $plan->team_seats > 1 ? 's' : '' }}@if ($plan->isSharedTeamPlan()) — shared across your whole offer list @endif</span>
                            </li>
                            @if ((int) $plan->wordpress_sites_limit !== 0)
                                <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> @if ((int) $plan->wordpress_sites_limit < 0) Unlimited WordPress sites @elseif ((int) $plan->wordpress_sites_limit === 1) 1 WordPress site @else {{ $plan->wordpress_sites_limit }} WordPress sites @endif</li>
                            @endif
                            @if ((int) $plan->network_connections_limit !== 0)
                                <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> @if ((int) $plan->network_connections_limit < 0) Unlimited affiliate network connections @elseif ((int) $plan->network_connections_limit === 1) 1 affiliate network connection @else {{ $plan->network_connections_limit }} affiliate network connections @endif</li>
                            @endif
                            @if ((int) $plan->monthly_lead_allowance !== 0)
                                <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> @if ((int) $plan->monthly_lead_allowance < 0) Unlimited B2B leads @else {{ number_format($plan->monthly_lead_allowance) }} B2B leads found for you each month @endif</li>
                            @endif
                            @if ($plan->agents_included)
                                <li class="flex items-start gap-2"><x-heroicon-m-check-circle class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" aria-hidden="true" /> AI Agents{{ $plan->agent_autopilot ? ' with autopilot' : '' }} (Reply Copilot, Lead Hunter and more)</li>
                            @endif
                        </ul>

                        <a href="{{ route('registration.form', $plan) }}" class="mt-2 w-full rounded-full py-3 text-center text-sm font-semibold transition {{ $plan->is_featured ? 'bg-gold-400 text-navy-950 hover:bg-gold-300' : 'bg-navy-900 text-white hover:bg-navy-800' }}">
                            {{ SiteCopy::text('pricing_button_label') }}
                        </a>

                        @if (! empty($plan->channels) || $plan->active_products_limit)
                            <details class="group -mx-1">
                                <summary class="flex cursor-pointer list-none select-none items-center justify-center gap-1 px-1 py-1 text-xs font-medium {{ $plan->is_featured ? 'text-gold-300' : 'text-brand-600 hover:text-brand-700' }}">
                                    <span>{{ SiteCopy::text('pricing_details_label') }}</span>
                                    <span class="transition group-open:rotate-45" aria-hidden="true">+</span>
                                </summary>

                                <div class="mt-3 space-y-3 border-t pt-3 text-xs {{ $plan->is_featured ? 'border-white/15 text-white/75' : 'border-line text-ink-600' }}">
                                    <ul class="space-y-1">
                                        <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> {{ $plan->active_products_limit === 0 ? 'Unlimited active offers' : $plan->active_products_limit.' active offer'.($plan->active_products_limit > 1 ? 's' : '') }}</li>
                                        <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> Built-in referral / affiliate program</li>
                                        <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> Add-on marketplace access</li>
                                        <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> AffilStack in your language</li>
                                        <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> Email support</li>
                                    </ul>

                                    @if (! empty($plan->channels))
                                        <div>
                                            <p class="mb-1 font-medium {{ $plan->is_featured ? 'text-white' : 'text-navy-900' }}">Channels &amp; content types</p>
                                            <ul class="space-y-1">
                                                @foreach ($plan->channels as $channel)
                                                    <li class="flex items-start gap-1.5"><span class="text-emerald-500" aria-hidden="true">✓</span> {{ \App\Models\Plan::channelLabels()[$channel] ?? \Illuminate\Support\Str::headline($channel) }}</li>
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

            <p class="mt-8 text-center text-xs text-ink-400">{{ SiteCopy::text('pricing_footnote') }} See our <a href="{{ route('refund-policy') }}" class="underline hover:text-ink-600">refund policy</a> before you buy.</p>

            <div class="mx-auto mt-12 flex max-w-4xl flex-col items-center gap-5 rounded-3xl bg-surface-muted px-6 py-8 text-center ring-1 ring-line sm:flex-row sm:text-left" data-reveal>
                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white text-brand-600 ring-1 ring-line"><x-heroicon-o-chat-bubble-left-right class="h-6 w-6" aria-hidden="true" /></span>
                <div class="flex-1">
                    <h2 class="font-display text-lg font-semibold text-navy-900">{{ SiteCopy::text('pricing_faq_heading') }}</h2>
                    <p class="mt-1 text-sm text-ink-600">{{ SiteCopy::text('pricing_faq_text') }}</p>
                </div>
                <div class="flex flex-wrap justify-center gap-2">
                    <a href="{{ route('faq.index') }}" class="rounded-full bg-white px-5 py-2.5 text-sm font-semibold text-navy-900 ring-1 ring-line hover:bg-surface-muted">FAQ</a>
                    <a href="{{ \App\Support\Website\NavigationMenu::expertUrl() }}" class="rounded-full bg-navy-900 px-5 py-2.5 text-sm font-semibold text-white hover:bg-navy-800">{{ \App\Support\Website\NavigationMenu::label('nav_expert_label') }}</a>
                </div>
            </div>
        </div>
    </section>
@endsection
