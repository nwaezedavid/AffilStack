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
        <script type="application/ld+json">{!! json_encode($faqJsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}</script>
    @endpush
@endif

@section('content')
    <div class="max-w-3xl mx-auto px-6 py-14">
        <h1 class="font-display text-3xl font-semibold text-navy-900 mb-2">Help & Support</h1>
        <p class="text-ink-600 mb-8">Search the FAQs below, or reach us directly — whichever is fastest for you.</p>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-12">
            <a href="mailto:{{ $supportEmail }}" class="block bg-surface border border-line rounded-lg p-5 hover:border-brand-400 transition">
                <span class="block text-sm font-semibold text-ink-900 mb-1">Email us</span>
                <span class="block text-xs text-ink-500 mb-3">For anything that isn't urgent.</span>
                <span class="block text-sm text-brand-600 break-all">{{ $supportEmail }}</span>
            </a>
            <a href="{{ route('support.chat') }}" class="block bg-surface border border-line rounded-lg p-5 hover:border-brand-400 transition">
                <span class="block text-sm font-semibold text-ink-900 mb-1">Chat with support</span>
                <span class="block text-xs text-ink-500 mb-3">Our AI assistant knows the platform and answers instantly. It can hand you to a human ticket any time.</span>
                <span class="block text-sm text-brand-600">Start a chat &rarr;</span>
            </a>
            <a href="{{ route('support.create') }}" class="block bg-surface border border-line rounded-lg p-5 hover:border-brand-400 transition">
                <span class="block text-sm font-semibold text-ink-900 mb-1">Open a ticket</span>
                <span class="block text-xs text-ink-500 mb-3">For account, billing, or technical issues you'd like our team to track.</span>
                <span class="block text-sm text-brand-600">Open a ticket &rarr;</span>
            </a>
        </div>
        <p class="text-xs text-ink-400 -mt-8 mb-12">Chat and tickets require you to be signed in — you'll be sent to log in first if you aren't.</p>

        <h2 class="font-display text-xl font-semibold text-navy-900 mb-4">Frequently asked questions</h2>

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
