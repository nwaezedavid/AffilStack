@extends('layouts.marketing')
@use('App\Support\Website\SiteCopy')

{{-- /help — the Help Centre: support channels, guides and a few popular
     questions (the full list, with FAQPage schema, is /faq). Every word is
     edited in Website → Help Centre. --}}
@section('title', 'Help Centre · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', SiteCopy::text('help_subheading'))

@include('marketing.partials.motion')

@php
    $popular = $groups->flatten(1)->sortBy('sort_order')->take(5)->values();
    $channels = [
        ['title' => SiteCopy::text('help_chat_title'), 'text' => SiteCopy::text('help_chat_text'), 'url' => route('support.chat'), 'icon' => 'chat-bubble-left-right', 'action' => 'Start a chat', 'tile' => 'bg-brand-50 text-brand-600 ring-brand-500/20'],
        ['title' => SiteCopy::text('help_ticket_title'), 'text' => SiteCopy::text('help_ticket_text'), 'url' => route('support.create'), 'icon' => 'ticket', 'action' => 'Open a ticket', 'tile' => 'bg-violet-50 text-violet-600 ring-violet-500/20'],
        ['title' => SiteCopy::text('help_email_title'), 'text' => SiteCopy::text('help_email_text'), 'url' => 'mailto:'.$supportEmail, 'icon' => 'envelope', 'action' => $supportEmail, 'tile' => 'bg-emerald-50 text-emerald-600 ring-emerald-500/20'],
    ];
    $resources = [
        ['title' => SiteCopy::text('help_faq_title'), 'text' => SiteCopy::text('help_faq_text'), 'url' => route('faq.index'), 'icon' => 'question-mark-circle'],
        ['title' => SiteCopy::text('help_learn_title'), 'text' => SiteCopy::text('help_learn_text'), 'url' => route('tutorials.index'), 'icon' => 'academic-cap'],
        ['title' => SiteCopy::text('help_features_title'), 'text' => SiteCopy::text('help_features_text'), 'url' => route('features.index'), 'icon' => 'squares-2x2'],
        ['title' => SiteCopy::text('help_contact_title'), 'text' => SiteCopy::text('help_contact_text'), 'url' => route('contact.show'), 'icon' => 'user-circle'],
    ];
@endphp

@section('content')
    <section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="help-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0" style="background: radial-gradient(60% 80% at 80% 0%, rgba(36,82,217,.55) 0%, transparent 65%), radial-gradient(40% 60% at 0% 100%, rgba(16,185,129,.25) 0%, transparent 70%), radial-gradient(40% 60% at 100% 100%, rgba(201,162,74,.3) 0%, transparent 70%);"></div>
            <div class="home-grid-pattern absolute inset-0"></div>
        </div>
        <div class="mx-auto max-w-3xl px-4 pt-16 pb-28 text-center sm:px-6 lg:pt-20">
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">{{ SiteCopy::text('help_eyebrow') }}</p>
            <h1 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl" id="help-heading">{{ SiteCopy::highlight('help_heading', 'text-gold-300') }}</h1>
            <p class="mx-auto mt-5 max-w-2xl text-base text-white/75 sm:text-lg">{{ SiteCopy::text('help_subheading') }}</p>
        </div>
    </section>

    <section class="relative -mt-16 pb-6" aria-label="Support channels">
        <div class="mx-auto grid max-w-6xl gap-5 px-4 sm:px-6 md:grid-cols-3 lg:px-8">
            @foreach ($channels as $channel)
                <a href="{{ $channel['url'] }}" class="group flex flex-col rounded-3xl bg-white p-6 shadow-[0_24px_48px_-28px_rgba(11,30,61,.45)] ring-1 ring-line transition hover:-translate-y-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" data-reveal>
                    <span class="flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $channel['tile'] }}">
                        @include('marketing.home.partials.icon', ['icon' => $channel['icon'], 'class' => 'h-6 w-6'])
                    </span>
                    <h2 class="mt-5 font-display text-lg font-semibold text-navy-900">{{ $channel['title'] }}</h2>
                    <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{{ $channel['text'] }}</p>
                    <span class="mt-5 inline-flex items-center gap-1 break-all text-sm font-semibold text-brand-600 group-hover:text-brand-700">{{ $channel['action'] }} <x-heroicon-m-arrow-right class="h-4 w-4 shrink-0" aria-hidden="true" /></span>
                </a>
            @endforeach
        </div>
        <p class="mx-auto mt-5 max-w-6xl px-4 text-center text-xs text-ink-400 sm:px-6 lg:px-8">{{ SiteCopy::text('help_signin_note') }}</p>
    </section>

    <section class="bg-white py-14 sm:py-20" aria-labelledby="help-resources-heading">
        <div class="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
            <h2 id="help-resources-heading" class="font-display text-2xl font-semibold tracking-tight text-navy-900">{{ SiteCopy::text('help_resources_heading') }}</h2>
            <div class="mt-8 grid gap-4 sm:grid-cols-2">
                @foreach ($resources as $resource)
                    <a href="{{ $resource['url'] }}" class="group flex items-start gap-4 rounded-2xl bg-surface-muted p-5 ring-1 ring-line transition hover:bg-white hover:shadow-[0_20px_40px_-28px_rgba(11,30,61,.45)]" data-reveal>
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-brand-600 ring-1 ring-line transition group-hover:bg-brand-600 group-hover:text-white">
                            @include('marketing.home.partials.icon', ['icon' => $resource['icon'], 'class' => 'h-5 w-5'])
                        </span>
                        <span>
                            <span class="block font-display text-base font-semibold text-navy-900">{{ $resource['title'] }}</span>
                            <span class="mt-1 block text-sm text-ink-600">{{ $resource['text'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </div>
    </section>

    @if ($popular->isNotEmpty())
        <section class="bg-surface-muted py-14 sm:py-20" aria-labelledby="help-popular-heading">
            <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <h2 id="help-popular-heading" class="font-display text-2xl font-semibold tracking-tight text-navy-900">{{ SiteCopy::text('help_popular_heading') }}</h2>
                    <a href="{{ route('faq.index') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-brand-600 hover:text-brand-700">All questions <x-heroicon-m-arrow-right class="h-4 w-4" aria-hidden="true" /></a>
                </div>
                <div class="mt-6 space-y-3">
                    @foreach ($popular as $item)
                        <details class="group rounded-2xl bg-white ring-1 ring-line transition open:ring-brand-200">
                            <summary class="flex cursor-pointer list-none items-center justify-between gap-4 rounded-2xl px-6 py-4 text-sm font-semibold text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden">
                                {{ $item->question }}
                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-surface-muted transition group-open:rotate-45 group-open:bg-brand-600 group-open:text-white" aria-hidden="true"><x-heroicon-m-plus class="h-4 w-4" /></span>
                            </summary>
                            <div class="whitespace-pre-line px-6 pb-5 text-sm leading-relaxed text-ink-600">{{ $item->answer }}</div>
                        </details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
