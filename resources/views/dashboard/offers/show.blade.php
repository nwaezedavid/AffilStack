@extends('layouts.app')

@section('title', $offer->product_name)

@section('content')
    @php
        $disclosure = app(\App\Services\Compliance\DisclosureService::class);
        $moduleLabels = [
            'blog_article' => 'Blog article', 'competitor_angles' => 'Competitor angles', 'linkedin_keywords' => 'LinkedIn keywords',
            'linkedin_dm_sequence' => 'LinkedIn DM sequence', 'linkedin_post' => 'LinkedIn post', 'linkedin_article' => 'LinkedIn article',
            'youtube_script' => 'YouTube script', 'youtube_metadata' => 'YouTube metadata', 'ugc_angles' => 'UGC angles',
            'ugc_content' => 'UGC script', 'ugc_video' => 'UGC video', 'x_thread' => 'X thread', 'tiktok_video' => 'TikTok package',
            'pinterest_pin' => 'Pinterest pins', 'email_nurture' => 'Email nurture',
        ];
    @endphp

    <nav aria-label="Breadcrumb" class="mb-3 flex items-center gap-1.5 text-xs text-ink-600">
        @if (! auth()->user()->isSeat() || auth()->user()->hasSharedTeamAccess())
            <a href="{{ route('offers.index') }}" class="hover:text-brand-600">My Products</a>
            <x-heroicon-m-chevron-right class="h-3.5 w-3.5 text-ink-400" />
        @endif
        <span class="truncate font-medium text-navy-900">{{ $offer->product_name }}</span>
    </nav>

    <x-ui.page-header :title="$offer->product_name" :eyebrow="$offer->affiliate_network.' · Product hub'" icon="cube"
                      subtitle="Everything for this product in one place: the AI research, your prospects, and every tool to promote it.">
        <x-slot:actions>
            <x-ui.button :href="$offer->product_url" variant="light" icon="arrow-top-right-on-square" target="_blank" rel="noopener">Visit product</x-ui.button>
            @if ($offer->affiliate_link)
                <x-ui.copy-button :text="$offer->affiliate_link" label="Copy affiliate link" tone="light" size="md" />
                <a href="{{ $offer->affiliate_link }}" target="_blank" rel="noopener" class="sr-only">{{ $offer->affiliate_link }}</a>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Disclosure jurisdiction — governs which affiliate-disclosure wording
         is auto-inserted into this product's generated content. --}}
    <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-line bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-3">
            <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600">
                <x-heroicon-o-shield-check class="h-5 w-5" />
            </span>
            <div>
                <p class="text-sm font-semibold text-navy-900">Affiliate disclosure</p>
                <p class="text-xs text-ink-600">Content for this product auto-includes the right disclosure wording for this audience. Not legal advice — review before publishing.</p>
            </div>
        </div>
        @if (auth()->user()->isSeat())
            <span class="text-sm text-ink-900">{{ $disclosure->countries()[$offer->disclosure_country] ?? $offer->disclosure_country }}</span>
        @else
            <form method="POST" action="{{ route('offers.disclosure.update', $offer) }}" class="flex items-center gap-2">
                @csrf
                @method('PATCH')
                <label for="disclosure_country" class="sr-only">Disclosure jurisdiction</label>
                <select id="disclosure_country" name="disclosure_country" onchange="this.form.submit()" class="rounded-full border border-line bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @foreach ($disclosure->countries() as $code => $label)
                        <option value="{{ $code }}" @selected($offer->disclosure_country === $code)>{{ $label }}</option>
                    @endforeach
                </select>
                <noscript><button class="rounded-full border border-line px-3 py-1.5 text-xs hover:bg-surface-muted">Save</button></noscript>
            </form>
        @endif
    </div>

    <div class="mb-8">
        @include('dashboard.offers.partials.results', ['context' => 'hub'])
    </div>

    {{-- Recent content --}}
    @if ($recentGenerations->isNotEmpty())
        <section class="mb-8">
            <div class="mb-3 flex items-end justify-between">
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-600">Your work</p>
                    <h3 class="font-display text-lg font-semibold text-navy-900">Recent content for this product</h3>
                </div>
                <a href="{{ route('calendar.index') }}" class="text-sm font-medium text-brand-600 hover:text-brand-700">Content calendar &rarr;</a>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recentGenerations as $gen)
                    @php
                        $genFeature = \App\Support\OfferFeatures::keyForModule($gen->module);
                        $genTone = $genFeature ? \App\Support\OfferFeatures::tone(\App\Support\OfferFeatures::get($genFeature)['tone']) : \App\Support\OfferFeatures::tone('brand');
                    @endphp
                    <a href="{{ $genFeature ? route('offers.feature', [$offer, $genFeature]) : route('offers.show', $offer) }}"
                       class="group flex min-w-0 items-start gap-3 rounded-2xl border border-line bg-white p-4 transition hover:-translate-y-0.5 hover:shadow-md">
                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $genTone['icon'] }}">
                            <x-dynamic-component :component="'heroicon-o-'.($genFeature ? \App\Support\OfferFeatures::get($genFeature)['icon'] : 'document')" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-xs font-medium {{ $genTone['text'] }}">{{ $moduleLabels[$gen->module] ?? str_replace('_', ' ', $gen->module) }}{{ $gen->target_market ? ' · localized' : '' }}</span>
                            <span class="block truncate text-sm font-semibold text-navy-900">{{ $gen->calendarTitle() }}</span>
                            <span class="mt-1 flex items-center gap-2 text-xs text-ink-400">
                                @if ($gen->status === 'queued')
                                    <x-ui.status-pill status="checking" label="Generating…" />
                                @elseif ($gen->status === 'failed')
                                    <x-ui.status-pill status="failed" label="Failed" />
                                @else
                                    <x-ui.status-pill status="verified" label="Ready" />
                                @endif
                                {{ $gen->created_at->diffForHumans() }}
                            </span>
                        </span>
                    </a>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Captured research (item 11) — read-only: attach/remove happens from
         the Browser Extension page, not here. --}}
    @if ($offer->researchClips->isNotEmpty())
        <x-ui.card title="Captured research" description="Pages and snippets you saved with the Chrome extension." icon="bookmark" class="mb-8" padding="p-0">
            <div class="divide-y divide-line">
                @foreach ($offer->researchClips as $clip)
                    <div class="p-4 sm:px-6">
                        <div class="flex flex-wrap items-center gap-2 text-xs font-mono uppercase tracking-wide text-ink-400">
                            <span>{{ config('extension.page_types')[$clip->page_type] ?? $clip->page_type }}</span>
                            <span>&middot;</span>
                            <span>{{ $clip->created_at->format('M j, Y') }}</span>
                        </div>
                        <div class="mt-1 text-sm font-medium text-ink-900">{{ $clip->title ?: $clip->source_url }}</div>
                        <a href="{{ $clip->source_url }}" target="_blank" rel="noopener" class="break-all text-xs text-brand-600 hover:text-brand-700">{{ $clip->source_url }}</a>
                        @if ($clip->selected_text)
                            <p class="mt-2 line-clamp-3 text-sm text-ink-600">{{ $clip->selected_text }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </x-ui.card>
    @endif
@endsection

@push('scripts')
    @include('dashboard.offers.partials.results-script')
@endpush
