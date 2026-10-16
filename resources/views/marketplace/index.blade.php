@extends('layouts.marketing')

{{-- affilstack.com/marketplace — approved add-ons from developers. --}}
@section('title', 'Add-on Marketplace · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', 'Connected apps, template packs and automation recipes that extend AffilStack — built by independent developers and reviewed by our team.')

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="marketplace-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(55% 70% at 10% 0%, rgba(36,82,217,.5) 0%, transparent 60%), radial-gradient(40% 55% at 95% 100%, rgba(201,162,74,.3) 0%, transparent 65%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-5xl px-4 pt-16 pb-12 text-center sm:px-6 lg:px-8 lg:pt-20">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 font-mono text-[11px] font-medium uppercase tracking-[0.16em] text-gold-300 ring-1 ring-white/15">Marketplace</p>
            <h1 id="marketplace-heading" class="mt-5 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Add more to <span class="bg-gradient-to-r from-gold-300 to-amber-200 bg-clip-text text-transparent">AffilStack</span></h1>
            <p class="mx-auto mt-5 max-w-2xl text-base text-white/75 sm:text-lg">Apps that connect to your account, ready-made templates and automation recipes — built by independent developers, and every one reviewed by our team before it's listed.</p>
        </div>
    </section>

    <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
        @include('partials.marketplace.filters', ['action' => route('marketplace.index'), 'types' => $types, 'categories' => $categories])

        @if ($addons->isEmpty())
            <div class="mt-10 rounded-2xl border border-dashed border-line bg-surface px-6 py-14 text-center">
                <p class="font-display text-lg font-semibold text-navy-900">{{ request()->hasAny(['q', 'type', 'category']) ? 'No add-ons match your search.' : 'The first add-ons are on their way.' }}</p>
                <p class="mt-2 text-sm text-ink-600">Are you a developer? <a href="{{ route('developer.home') }}" class="font-semibold text-brand-600 hover:text-brand-700">Build an add-on</a> and earn from every subscription.</p>
            </div>
        @else
            <div class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($addons as $addon)
                    @include('partials.marketplace.addon-card', ['addon' => $addon, 'href' => route('marketplace.show', $addon)])
                @endforeach
            </div>
            <div class="mt-8">{{ $addons->links() }}</div>
        @endif

        <div class="mt-14 flex flex-col items-start justify-between gap-4 rounded-2xl bg-gradient-to-r from-navy-900 to-brand-700 p-6 text-white sm:flex-row sm:items-center sm:p-8">
            <div>
                <p class="font-display text-lg font-semibold">Build for AffilStack</p>
                <p class="mt-1 text-sm text-white/75">Reach affiliate marketers with your app, templates or automations. Keep {{ rtrim(rtrim(number_format(\App\Support\Marketplace\MarketplaceSettings::revenueSharePercent(), 2), '0'), '.') }}% of every subscription.</p>
            </div>
            <a href="{{ route('developer.home') }}" class="inline-flex items-center gap-2 rounded-full bg-gold-400 px-5 py-2.5 text-sm font-semibold text-navy-950 hover:bg-gold-300">Developer portal <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
        </div>
    </section>
@endsection
