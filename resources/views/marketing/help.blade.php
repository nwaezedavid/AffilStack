@extends('layouts.marketing')

@section('title', 'Help & FAQ · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))

@section('meta_description', 'Answers to common questions about AffilStack — offer research, LinkedIn content, blog articles, billing, and your account.')

<?php
    // FAQPage rich-result eligibility just needs every question/answer
    // pair actually visible on the page, which the <details> list below
    // already is — same "only claim what's genuinely on the page" rule
    // the homepage's AggregateRating already follows.
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
        <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>
    @endpush
@endif

@section('content')
    <div class="max-w-3xl mx-auto px-6 py-14">
        <h1 class="font-display text-3xl font-semibold text-navy-900 mb-2">Help & FAQ</h1>
        <p class="text-ink-600 mb-10">Can't find what you need? Our <a href="{{ route('login') }}" class="text-brand-600 underline">AI assistant</a> in the dashboard can help instantly, or you can open a support ticket once signed in.</p>

        @forelse ($groups as $category => $items)
            <div class="mb-10">
                <h2 class="text-xs font-mono uppercase tracking-wide text-ink-400 mb-3">{{ ucfirst($category) }}</h2>
                <div class="space-y-3">
                    @foreach ($items as $item)
                        <details class="bg-surface border border-line rounded-lg p-4 group">
                            <summary class="text-sm font-medium text-ink-900 cursor-pointer list-none flex items-center justify-between">
                                {{ $item->question }}
                                <span class="text-ink-400 group-open:rotate-45 transition">+</span>
                            </summary>
                            <p class="text-sm text-ink-600 mt-3 whitespace-pre-line">{{ $item->answer }}</p>
                        </details>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-ink-400">No FAQs published yet.</p>
        @endforelse
    </div>
@endsection
