@extends('layouts.developer')

@section('title', 'Build for AffilStack')

@php($sharePercent = rtrim(rtrim(number_format($share, 2), '0'), '.'))

@section('hero')
    <section class="relative overflow-hidden bg-navy-950 px-4 pb-16 pt-14 text-white sm:px-6 lg:px-8">
        <div class="absolute inset-0" aria-hidden="true" style="background: radial-gradient(50% 60% at 85% 0%, rgba(36,82,217,.45) 0%, transparent 60%), radial-gradient(35% 50% at 0% 100%, rgba(201,162,74,.25) 0%, transparent 65%);"></div>
        <div class="relative mx-auto grid max-w-6xl items-center gap-10 lg:grid-cols-2">
            <div>
                <p class="font-mono text-xs uppercase tracking-[0.18em] text-gold-300">AffilStack Developer Platform</p>
                <h1 class="mt-4 font-display text-4xl font-semibold leading-tight tracking-tight text-balance sm:text-5xl">Build what affiliate marketers need next.</h1>
                <p class="mt-5 max-w-xl text-base text-white/75 sm:text-lg">Publish connected apps, template packs and automation recipes in the AffilStack marketplace. We handle billing — you keep {{ $sharePercent }}% of every subscription.</p>
                <div class="mt-8 flex flex-wrap gap-3">
                    <a href="{{ auth()->check() ? route('developer.join') : route('developer.login') }}" class="inline-flex items-center gap-2 rounded-full bg-gold-400 px-6 py-3 text-sm font-semibold text-navy-950 hover:bg-gold-300">Start building <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                    <a href="{{ route('developer.docs') }}" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-6 py-3 text-sm font-semibold text-white ring-1 ring-white/20 hover:bg-white/20">Read the docs</a>
                </div>
            </div>
            <div class="rounded-2xl bg-black/30 p-5 font-mono text-[12.5px] leading-relaxed text-white/85 ring-1 ring-white/10 shadow-2xl">
                <p class="text-white/40"># Exchange the code for tokens</p>
                <p><span class="text-gold-300">POST</span> /api/oauth/token</p>
                <p class="mt-2 text-white/60">grant_type=authorization_code</p>
                <p class="text-white/60">client_id=afapp_…&amp;client_secret=afsec_…</p>
                <p class="mt-3 text-white/40"># Then call the API for that customer</p>
                <p><span class="text-gold-300">GET</span> /api/v1/offers</p>
                <p class="text-white/60">Authorization: Bearer afat_…</p>
                <p class="mt-3 text-emerald-300">{ "data": [ { "product_name": "…" } ] }</p>
            </div>
        </div>
    </section>
@endsection

@section('content')
    <section class="pb-14 pt-6" aria-labelledby="build-heading">
        <h2 id="build-heading" class="font-display text-2xl font-semibold text-navy-900">Three ways to build</h2>
        <div class="mt-6 grid gap-4 md:grid-cols-3">
            @foreach ([
                'app' => ['icon' => 'link', 'points' => ['"Connect with AffilStack" (OAuth 2.0)', 'Read and write offers, content and CRM — only what customers allow', 'Webhooks when things happen']],
                'template_pack' => ['icon' => 'document-duplicate', 'points' => ['Prompts, emails, posts, hooks and scripts', 'Installed into the customer\'s template library', 'No code needed']],
                'automation' => ['icon' => 'bolt', 'points' => ['Zapier, Make or n8n workflows', 'Share link or blueprint file', 'Step-by-step setup for customers']],
            ] as $key => $type)
                <div class="rounded-2xl border border-line bg-surface p-6">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-dynamic-component :component="'heroicon-o-'.$type['icon']" class="h-5 w-5" aria-hidden="true" /></span>
                    <h3 class="mt-4 font-display text-lg font-semibold text-navy-900">{{ $types[$key]['label'] }}</h3>
                    <p class="mt-1 text-sm text-ink-600">{{ $types[$key]['description'] }}</p>
                    <ul class="mt-4 space-y-1.5 text-sm text-ink-600">
                        @foreach ($type['points'] as $point)
                            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> {{ $point }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </section>

    <section class="grid gap-6 border-t border-line py-14 lg:grid-cols-5" aria-labelledby="how-heading">
        <div class="lg:col-span-2">
            <h2 id="how-heading" class="font-display text-2xl font-semibold text-navy-900">From idea to income</h2>
            <p class="mt-3 text-sm text-ink-600">Sign in with your AffilStack account — no separate sign-up. Every listing and update is reviewed by our team before customers see it, so the marketplace stays trustworthy.</p>
        </div>
        <ol class="grid gap-3 sm:grid-cols-2 lg:col-span-3">
            @foreach ([
                ['Create', 'Register as a developer and create your add-on. Apps get their client ID and secret straight away.'],
                ['Test', 'Connect your own app to your own account before anyone else can see it.'],
                ['Submit', 'Send the listing for review. We check it works, says what it does, and treats customer data properly.'],
                ['Earn', 'Customers subscribe monthly through AffilStack. You get '.$sharePercent.'% of each payment, paid out '.$holdDays.' days later.'],
            ] as $index => [$title, $text])
                <li class="rounded-2xl border border-line bg-surface p-5">
                    <span class="font-mono text-xs text-gold-600">Step {{ $index + 1 }}</span>
                    <p class="mt-1 font-semibold text-navy-900">{{ $title }}</p>
                    <p class="mt-1 text-sm text-ink-600">{{ $text }}</p>
                </li>
            @endforeach
        </ol>
    </section>

    <section class="rounded-2xl bg-gradient-to-r from-navy-900 to-brand-700 p-8 text-white">
        <div class="flex flex-col items-start justify-between gap-6 md:flex-row md:items-center">
            <div>
                <p class="font-display text-3xl font-semibold">{{ $sharePercent }}% to you</p>
                <p class="mt-2 max-w-xl text-sm text-white/75">Free or monthly pricing — you choose. AffilStack bills customers in US dollars or naira, handles failed payments and refunds, and pays you by Nigerian bank transfer, PayPal or USDT.</p>
            </div>
            <a href="{{ auth()->check() ? route('developer.join') : route('developer.login') }}" class="inline-flex items-center gap-2 rounded-full bg-gold-400 px-6 py-3 text-sm font-semibold text-navy-950 hover:bg-gold-300">Get started</a>
        </div>
    </section>
@endsection
