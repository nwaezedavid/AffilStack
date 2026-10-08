@extends('layouts.marketing')
@use('App\Support\Website\SiteCopy')

{{-- /faq — every published FAQ (Website → FAQ questions), grouped by
     category, with FAQPage structured data. Page copy: Website → FAQ page. --}}
@section('title', SiteCopy::plain('faq_heading').' · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', SiteCopy::text('faq_subheading'))

@include('marketing.partials.motion')

<?php
    // FAQPage rich results need every question/answer visible on the page,
    // which the list below is.
    $faqJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => $groups->flatten()->map(fn ($item) => [
            '@type' => 'Question',
            'name' => $item->question,
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $item->answer],
        ])->values()->all(),
    ];
?>
@if (! empty($faqJsonLd['mainEntity']))
    @push('head')
        <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
@endif

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="faq-page-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(60% 80% at 20% 0%, rgba(36,82,217,.55) 0%, transparent 65%), radial-gradient(40% 60% at 90% 100%, rgba(201,162,74,.3) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-3xl px-4 pt-16 pb-14 text-center sm:px-6 lg:pt-20">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">{{ SiteCopy::text('faq_eyebrow') }}</p>
            <h1 id="faq-page-heading" class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">{{ SiteCopy::highlight('faq_heading', 'text-gold-300') }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-base text-white/75 sm:text-lg">{{ SiteCopy::text('faq_subheading') }}</p>
            @if ($groups->isNotEmpty())
                <div class="relative mx-auto mt-8 max-w-xl">
                    <label for="faq-search" class="sr-only">{{ SiteCopy::text('faq_search_placeholder') }}</label>
                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-4 top-1/2 h-5 w-5 -translate-y-1/2 text-ink-400" aria-hidden="true" />
                    <input id="faq-search" type="search" placeholder="{{ SiteCopy::text('faq_search_placeholder') }}" autocomplete="off"
                           class="w-full rounded-full border-0 bg-white py-3.5 pl-12 pr-5 text-sm text-navy-900 shadow-xl ring-1 ring-white/20 placeholder:text-ink-400 focus:outline-none focus:ring-2 focus:ring-gold-400" data-faq-search>
                </div>
            @endif
        </div>
    </section>

    <section class="bg-surface-muted py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-10 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
            @if ($groups->count() > 1)
                <aside class="lg:col-span-3">
                    <nav class="lg:sticky lg:top-24" aria-label="FAQ categories">
                        <p class="font-mono text-[11px] uppercase tracking-[0.18em] text-ink-400">Topics</p>
                        <ul class="mt-3 flex flex-wrap gap-2 lg:flex-col lg:gap-1" role="list">
                            @foreach ($groups as $category => $items)
                                <li>
                                    <a href="#faq-{{ \Illuminate\Support\Str::slug($category) }}" class="flex items-center justify-between gap-3 rounded-full bg-white px-4 py-2 text-sm font-medium text-navy-900 ring-1 ring-line transition hover:ring-brand-300 lg:rounded-xl">
                                        {{ ucfirst($category) }}
                                        <span class="font-mono text-xs text-ink-400">{{ $items->count() }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </nav>
                </aside>
            @endif

            <div class="{{ $groups->count() > 1 ? 'lg:col-span-9' : 'lg:col-span-12' }} space-y-12">
                @forelse ($groups as $category => $items)
                    <div id="faq-{{ \Illuminate\Support\Str::slug($category) }}" class="scroll-mt-24" data-faq-group>
                        <h2 class="font-display text-xl font-semibold text-navy-900">{{ ucfirst($category) }}</h2>
                        <div class="mt-4 space-y-3">
                            @foreach ($items as $item)
                                <details class="group rounded-2xl bg-white ring-1 ring-line transition open:shadow-[0_16px_40px_-28px_rgba(11,30,61,.45)] open:ring-brand-200" data-faq-item>
                                    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-6 py-5 font-display text-base font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                                        {{ $item->question }}
                                        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-surface-muted text-navy-900 transition group-open:rotate-45 group-open:bg-brand-600 group-open:text-white" aria-hidden="true"><x-heroicon-m-plus class="h-4 w-4" /></span>
                                    </summary>
                                    <div class="whitespace-pre-line px-6 pb-6 text-sm leading-relaxed text-ink-600">{{ $item->answer }}</div>
                                </details>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="rounded-2xl bg-white p-8 text-center text-sm text-ink-600 ring-1 ring-line">No questions published yet.</p>
                @endforelse
                <p class="hidden rounded-2xl bg-white p-8 text-center text-sm text-ink-600 ring-1 ring-line" data-faq-empty>No question matches that search — try another word, or ask us directly below.</p>
            </div>
        </div>
    </section>

    <section class="bg-white py-16">
        <div class="mx-auto flex max-w-4xl flex-col items-center gap-6 rounded-3xl bg-navy-950 px-6 py-10 text-center text-white sm:flex-row sm:px-10 sm:text-left" data-reveal>
            <span class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-white/10 text-gold-300 ring-1 ring-white/15"><x-heroicon-o-lifebuoy class="h-7 w-7" aria-hidden="true" /></span>
            <div class="flex-1">
                <h2 class="font-display text-xl font-semibold">{{ SiteCopy::text('faq_help_heading') }}</h2>
                <p class="mt-1 text-sm text-white/70">{{ SiteCopy::text('faq_help_text') }}</p>
            </div>
            <a href="{{ route('help.index') }}" class="inline-flex items-center gap-2 rounded-full bg-gold-400 px-6 py-3 text-sm font-semibold text-navy-950 transition hover:bg-gold-300">{{ SiteCopy::text('faq_help_label') }} <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
        </div>
    </section>

    <script>
        (function () {
            var input = document.querySelector('[data-faq-search]');
            if (! input) { return; }
            input.addEventListener('input', function () {
                var term = input.value.trim().toLowerCase();
                var shown = 0;
                document.querySelectorAll('[data-faq-group]').forEach(function (group) {
                    var visibleInGroup = 0;
                    group.querySelectorAll('[data-faq-item]').forEach(function (item) {
                        var match = term === '' || item.textContent.toLowerCase().indexOf(term) !== -1;
                        item.classList.toggle('hidden', ! match);
                        if (match) { visibleInGroup++; if (term !== '') { item.open = true; } }
                    });
                    group.classList.toggle('hidden', visibleInGroup === 0);
                    shown += visibleInGroup;
                });
                document.querySelector('[data-faq-empty]').classList.toggle('hidden', shown > 0);
            });
        })();
    </script>
@endsection
