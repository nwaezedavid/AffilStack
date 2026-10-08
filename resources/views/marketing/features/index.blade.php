@extends('layouts.marketing')
@use('App\Support\Website\SiteCopy')
@use('App\Support\Website\NavigationMenu')

{{-- /features — every published tool, grouped like the header's Tools menu.
     Copy: Website → Features → "Overview page" tab; tools: Website → Features. --}}
@section('title', SiteCopy::plain('features_heading').' · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', SiteCopy::text('features_subheading'))

@include('marketing.partials.motion')

@php
    $tones = [
        'research' => ['tile' => 'bg-brand-50 text-brand-600 ring-brand-500/20', 'chip' => 'text-brand-700 bg-brand-50 ring-brand-500/20', 'bar' => 'from-brand-500 to-sky-400'],
        'outreach' => ['tile' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/20', 'chip' => 'text-emerald-700 bg-emerald-50 ring-emerald-500/20', 'bar' => 'from-emerald-500 to-teal-400'],
        'content' => ['tile' => 'bg-violet-50 text-violet-600 ring-violet-500/20', 'chip' => 'text-violet-700 bg-violet-50 ring-violet-500/20', 'bar' => 'from-violet-500 to-fuchsia-400'],
        'grow' => ['tile' => 'bg-amber-50 text-amber-600 ring-amber-500/25', 'chip' => 'text-amber-700 bg-amber-50 ring-amber-500/25', 'bar' => 'from-gold-500 to-amber-300'],
    ];
    $collectionLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => SiteCopy::plain('features_heading'),
        'itemListElement' => $pages->values()->map(fn ($page, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'name' => $page->name,
            'url' => $page->url(),
        ])->all(),
    ];
@endphp

@push('head')
    <script type="application/ld+json">{!! json_encode($collectionLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
@endpush

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="features-hero-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(60% 70% at 15% 10%, rgba(36,82,217,.55) 0%, transparent 60%), radial-gradient(45% 55% at 90% 85%, rgba(201,162,74,.32) 0%, transparent 65%), radial-gradient(40% 40% at 70% 15%, rgba(124,58,237,.25) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-4xl px-4 pt-16 pb-14 text-center sm:px-6 sm:pt-20 lg:px-8 lg:pt-24">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 font-mono text-[11px] font-medium uppercase tracking-[0.16em] text-gold-300 ring-1 ring-white/15 backdrop-blur">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-400" aria-hidden="true"></span>
                {{ SiteCopy::text('features_eyebrow') }}
            </p>
            <h1 id="features-hero-heading" class="mt-6 font-display text-4xl font-semibold leading-[1.08] tracking-tight text-balance sm:text-5xl lg:text-6xl">
                {{ SiteCopy::highlight('features_heading', 'bg-gradient-to-r from-gold-300 via-amber-200 to-gold-400 bg-clip-text text-transparent') }}
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-base leading-relaxed text-white/75 sm:text-lg">{{ SiteCopy::text('features_subheading') }}</p>
            <div class="mt-9 flex flex-col items-center justify-center gap-3 sm:flex-row">
                <a href="{{ NavigationMenu::primaryCta()['url'] }}" class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-7 py-3.5 text-sm font-semibold text-navy-950 shadow-[0_12px_32px_-12px_rgba(211,168,84,.9)] transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                    {{ auth()->check() ? NavigationMenu::primaryCta()['label'] : SiteCopy::text('features_primary_label') }}
                    <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
                <a href="{{ NavigationMenu::expertUrl() }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                    <x-heroicon-m-chat-bubble-left-right class="h-5 w-5 text-gold-300" aria-hidden="true" />
                    {{ SiteCopy::text('features_secondary_label') }}
                </a>
            </div>
            @if (count($groups) > 1)
                <nav class="mt-12 flex flex-wrap items-center justify-center gap-2" aria-label="Jump to a group of features">
                    @foreach ($groups as $groupKey => $groupPages)
                        <a href="#group-{{ $groupKey }}" class="rounded-full bg-white/5 px-4 py-2 text-sm font-medium text-white/80 ring-1 ring-white/15 transition hover:bg-white/15 hover:text-white">
                            {{ NavigationMenu::label('nav_group_'.$groupKey.'_label') }}
                            <span class="ml-1 font-mono text-xs text-gold-300">{{ $groupPages->count() }}</span>
                        </a>
                    @endforeach
                </nav>
            @endif
        </div>
    </section>

    @foreach ($groups as $groupKey => $groupPages)
        @php $tone = $tones[$groupKey] ?? $tones['research']; @endphp
        <section id="group-{{ $groupKey }}" class="{{ $loop->even ? 'bg-surface-muted' : 'bg-white' }} py-16 sm:py-20" aria-labelledby="group-{{ $groupKey }}-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4" data-reveal>
                    <div>
                        <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-ink-400">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} / {{ str_pad((string) count($groups), 2, '0', STR_PAD_LEFT) }}</p>
                        <h2 id="group-{{ $groupKey }}-heading" class="mt-2 font-display text-2xl font-semibold tracking-tight text-navy-900 sm:text-3xl">{{ NavigationMenu::label('nav_group_'.$groupKey.'_label') }}</h2>
                    </div>
                    <div class="h-1 w-24 rounded-full bg-gradient-to-r {{ $tone['bar'] }}" aria-hidden="true"></div>
                </div>

                <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($groupPages as $page)
                        <a href="{{ $page->url() }}" class="group flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)] focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" data-reveal data-feature-card="{{ $page->slug }}">
                            @if ($image = $page->heroImageUrl())
                                <div class="relative aspect-[16/10] overflow-hidden border-b border-line bg-surface-muted">
                                    <img src="{{ $image }}" alt="" loading="lazy" decoding="async" class="h-full w-full object-cover object-top transition duration-700 group-hover:scale-[1.03] motion-reduce:transition-none">
                                </div>
                            @endif
                            <div class="flex flex-1 flex-col p-6">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tone['tile'] }}">
                                        @include('marketing.home.partials.icon', ['icon' => $page->icon, 'class' => 'h-5 w-5'])
                                    </span>
                                    <h3 class="font-display text-lg font-semibold leading-tight text-navy-900">{{ $page->name }}</h3>
                                </div>
                                <p class="mt-3 flex-1 text-sm leading-relaxed text-ink-600">{{ $page->menu_description }}</p>
                                <span class="mt-5 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 group-hover:text-brand-700">
                                    {{ SiteCopy::text('features_card_link_label') }} <span class="sr-only">{{ $page->name }}</span>
                                    <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                                </span>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endforeach

    @include('marketing.features.partials.cta', [
        'heading' => SiteCopy::text('features_cta_heading'),
        'text' => SiteCopy::text('features_cta_text'),
        'label' => auth()->check() ? NavigationMenu::primaryCta()['label'] : SiteCopy::text('features_cta_label'),
        'url' => NavigationMenu::primaryCta()['url'],
    ])
@endsection
