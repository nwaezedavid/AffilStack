@extends('layouts.marketing')
@use('App\Support\Homepage\HomepageContent')
@use('App\Support\Website\SiteCopy')
@use('App\Support\Website\NavigationMenu')

{{-- /features/{slug} — one tool in depth. Every word, screenshot and video
     slot is edited in Website → Features → (this feature). --}}
@php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $sections = $page->presentedSections();
    $steps = $page->presentedSteps();
    $faqs = $page->presentedFaqs();
    $heroImage = $page->heroImageUrl();
    $cta = NavigationMenu::primaryCta();
    $tone = [
        'research' => ['chip' => 'text-brand-700', 'tile' => 'bg-brand-50 text-brand-600 ring-brand-500/20', 'num' => 'bg-brand-600'],
        'outreach' => ['chip' => 'text-emerald-700', 'tile' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/20', 'num' => 'bg-emerald-600'],
        'content' => ['chip' => 'text-violet-700', 'tile' => 'bg-violet-50 text-violet-600 ring-violet-500/20', 'num' => 'bg-violet-600'],
        'grow' => ['chip' => 'text-amber-700', 'tile' => 'bg-amber-50 text-amber-600 ring-amber-500/25', 'num' => 'bg-gold-500'],
    ][$page->menu_group] ?? ['chip' => 'text-brand-700', 'tile' => 'bg-brand-50 text-brand-600 ring-brand-500/20', 'num' => 'bg-brand-600'];

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => route('home')],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Features', 'item' => route('features.index')],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $page->name, 'item' => $page->url()],
        ],
    ];
    $faqLd = $faqs === [] ? null : [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => ['@type' => 'Question', 'name' => $faq['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq['answer']]], $faqs),
    ];
@endphp

@section('title', ($page->seo_title ?: $page->name.' — '.HomepageContent::plain($page->hero_headline)).' · '.$siteName)
@section('meta_description', $page->meta_description ?: \Illuminate\Support\Str::limit((string) $page->hero_subheadline, 158))
@if ($heroImage)
    @section('og_image', $heroImage)
@endif

@include('marketing.partials.motion')

@push('head')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @if ($faqLd)
        <script type="application/ld+json">{!! json_encode($faqLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endif
@endpush

@section('content')
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="feature-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(60% 70% at 10% 10%, rgba(36,82,217,.55) 0%, transparent 60%), radial-gradient(45% 55% at 95% 90%, rgba(201,162,74,.3) 0%, transparent 65%), radial-gradient(40% 40% at 70% 10%, rgba(124,58,237,.22) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-7xl px-4 pt-8 sm:px-6 lg:px-8">
            <nav aria-label="Breadcrumb">
                <ol class="flex flex-wrap items-center gap-1.5 text-xs text-white/55" role="list">
                    <li><a href="{{ route('features.index') }}" class="hover:text-white">Features</a></li>
                    <li aria-hidden="true"><x-heroicon-m-chevron-right class="h-3.5 w-3.5" /></li>
                    <li aria-current="page" class="text-white/85">{{ $page->name }}</li>
                </ol>
            </nav>
        </div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-10 pb-20 sm:px-6 lg:grid-cols-12 lg:gap-12 lg:px-8 lg:pt-12 lg:pb-24">
            <div class="text-center lg:col-span-5 lg:text-left">
                <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 font-mono text-[11px] font-medium uppercase tracking-[0.16em] text-gold-300 ring-1 ring-white/15 backdrop-blur">
                    @include('marketing.home.partials.icon', ['icon' => $page->icon, 'class' => 'h-4 w-4'])
                    {{ $page->hero_eyebrow ?: $page->name }}
                </p>
                <h1 id="feature-heading" class="mt-6 font-display text-4xl font-semibold leading-[1.08] tracking-tight text-balance sm:text-5xl">
                    {{ HomepageContent::highlight($page->hero_headline, 'bg-gradient-to-r from-gold-300 via-amber-200 to-gold-400 bg-clip-text text-transparent') }}
                </h1>
                @if ($page->hero_subheadline)
                    <p class="mx-auto mt-6 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg lg:mx-0">{{ $page->hero_subheadline }}</p>
                @endif
                <div class="mt-9 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                    <a href="{{ $cta['url'] }}" class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-7 py-3.5 text-sm font-semibold text-navy-950 shadow-[0_12px_32px_-12px_rgba(211,168,84,.9)] transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                        {{ auth()->check() ? $cta['label'] : SiteCopy::text('feature_primary_label') }}
                        <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                    </a>
                    <a href="{{ route('features.index') }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                        <x-heroicon-m-squares-2x2 class="h-5 w-5 text-gold-300" aria-hidden="true" />
                        {{ SiteCopy::text('feature_secondary_label') }}
                    </a>
                </div>
            </div>
            <div class="lg:col-span-7">
                @if ($page->heroVideoEmbeddable())
                    <div class="relative">
                        <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-brand-500/40 via-violet-500/20 to-gold-400/40 blur-2xl" aria-hidden="true"></div>
                        <x-youtube-facade :url="$page->hero_video_url" :title="$page->name" :poster="$heroImage" :poster-alt="$page->hero_image_alt ?? ''" class="relative rounded-2xl shadow-2xl ring-1 ring-white/15" />
                    </div>
                @elseif ($heroImage)
                    <x-marketing.screenshot :src="$heroImage" :alt="$page->hero_image_alt ?: $page->name" tone="dark" eager data-feature-hero-image />
                @endif
            </div>
        </div>
    </section>

    {{-- Detailed sections: text beside a screenshot, alternating sides; a
         section's YouTube video (when added) plays under its screenshot. --}}
    @foreach ($sections as $section)
        <section class="{{ $loop->even ? 'bg-surface-muted' : 'bg-white' }} py-16 sm:py-24" aria-labelledby="section-{{ $loop->index }}-heading" data-feature-section>
            <div class="mx-auto grid max-w-7xl items-center gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:gap-16 lg:px-8">
                <div class="lg:col-span-5 {{ $loop->even ? 'lg:order-2' : '' }}" data-reveal>
                    <p class="font-mono text-[11px] uppercase tracking-[0.2em] {{ $tone['chip'] }}">{{ str_pad((string) $loop->iteration, 2, '0', STR_PAD_LEFT) }} · {{ $page->name }}</p>
                    <h2 id="section-{{ $loop->index }}-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance">{{ HomepageContent::highlight($section['heading'], 'text-brand-600') }}</h2>
                    @if ($section['body'] !== '')
                        <p class="mt-4 text-base leading-relaxed text-ink-600">{{ $section['body'] }}</p>
                    @endif
                    @if ($section['bullets'] !== [])
                        <ul class="mt-6 space-y-3" role="list">
                            @foreach ($section['bullets'] as $bullet)
                                <li class="flex items-start gap-3 text-sm text-navy-900">
                                    <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-emerald-100 text-emerald-700" aria-hidden="true"><x-heroicon-m-check class="h-3.5 w-3.5" /></span>
                                    {{ $bullet }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
                <div class="lg:col-span-7 {{ $loop->even ? 'lg:order-1' : '' }}" data-reveal>
                    @if ($section['image'])
                        <x-marketing.screenshot :src="$section['image']" :alt="$section['image_alt']" data-section-image />
                    @endif
                    @if ($section['video_url'])
                        <div class="{{ $section['image'] ? 'mt-6' : '' }}" data-section-video>
                            <p class="mb-3 inline-flex items-center gap-2 font-mono text-[11px] uppercase tracking-[0.18em] text-ink-400">
                                <x-heroicon-m-play-circle class="h-4 w-4 text-rose-500" aria-hidden="true" />
                                {{ SiteCopy::text('feature_video_label') }}
                            </p>
                            <x-youtube-facade :url="$section['video_url']" :title="$section['heading']" play-size="sm" class="rounded-2xl ring-1 ring-line shadow-[0_24px_48px_-24px_rgba(11,30,61,.4)]" />
                        </div>
                    @endif
                </div>
            </div>
        </section>
    @endforeach

    {{-- How to use it --}}
    @if ($steps !== [])
        <section class="bg-navy-950 py-16 text-white sm:py-24" aria-labelledby="steps-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="mx-auto max-w-2xl text-center" data-reveal>
                    <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">{{ $page->name }}</p>
                    <h2 id="steps-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight sm:text-4xl">{{ SiteCopy::text('feature_steps_heading') }}</h2>
                </div>
                <ol class="mt-12 grid gap-5 {{ count($steps) >= 4 ? 'sm:grid-cols-2 lg:grid-cols-4' : (count($steps) === 3 ? 'md:grid-cols-3' : 'sm:grid-cols-2') }}" role="list">
                    @foreach ($steps as $step)
                        <li class="relative rounded-3xl bg-white/5 p-6 ring-1 ring-white/10" data-reveal style="transition-delay: {{ $loop->index * 80 }}ms">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gold-400 font-display text-base font-semibold text-navy-950">{{ $loop->iteration }}</span>
                            <h3 class="mt-5 font-display text-lg font-semibold">{{ $step['title'] }}</h3>
                            @if ($step['description'] !== '')
                                <p class="mt-2 text-sm leading-relaxed text-white/70">{{ $step['description'] }}</p>
                            @endif
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>
    @endif

    {{-- FAQ --}}
    @if ($faqs !== [])
        <section class="bg-white py-16 sm:py-24" aria-labelledby="feature-faq-heading">
            <div class="mx-auto grid max-w-7xl gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
                <div class="lg:col-span-4" data-reveal>
                    <h2 id="feature-faq-heading" class="font-display text-3xl font-semibold tracking-tight text-navy-900">{{ SiteCopy::text('feature_faq_heading') }}</h2>
                    <a href="{{ route('faq.index') }}" class="mt-6 inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">All FAQs <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                </div>
                <div class="space-y-3 lg:col-span-8" data-reveal>
                    @foreach ($faqs as $faq)
                        <details class="group rounded-2xl bg-white ring-1 ring-line transition open:shadow-[0_16px_40px_-28px_rgba(11,30,61,.45)] open:ring-brand-200" @if ($loop->first) open @endif>
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-6 py-5 font-display text-base font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                                {{ $faq['question'] }}
                                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-navy-900 transition group-open:rotate-45 group-open:bg-brand-600 group-open:text-white" aria-hidden="true"><x-heroicon-m-plus class="h-4 w-4" /></span>
                            </summary>
                            <div class="px-6 pb-6 text-sm leading-relaxed text-ink-600">{{ $faq['answer'] }}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Related tools --}}
    @if ($related->isNotEmpty())
        <section class="bg-surface-muted py-16 sm:py-20" aria-labelledby="related-heading">
            <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-4">
                    <h2 id="related-heading" class="font-display text-2xl font-semibold tracking-tight text-navy-900">{{ SiteCopy::text('feature_related_heading') }}</h2>
                    <a href="{{ route('features.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">{{ NavigationMenu::label('nav_all_features_label') }} <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                </div>
                <div class="mt-8 grid gap-5 md:grid-cols-3">
                    @foreach ($related as $other)
                        <a href="{{ $other->url() }}" class="group flex items-start gap-4 rounded-3xl bg-white p-6 ring-1 ring-line transition hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]">
                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ring-1 {{ $tone['tile'] }}">
                                @include('marketing.home.partials.icon', ['icon' => $other->icon, 'class' => 'h-5 w-5'])
                            </span>
                            <span>
                                <span class="block font-display text-base font-semibold text-navy-900 group-hover:text-brand-700">{{ $other->name }}</span>
                                <span class="mt-1 block text-sm text-ink-600">{{ $other->menu_description }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @include('marketing.features.partials.cta', [
        'heading' => $page->cta_heading ?: SiteCopy::text('features_cta_heading'),
        'text' => $page->cta_text,
        'label' => auth()->check() ? $cta['label'] : ($page->cta_label ?: SiteCopy::text('features_cta_label')),
        'url' => auth()->check() ? $cta['url'] : $page->ctaUrl(),
    ])
@endsection
