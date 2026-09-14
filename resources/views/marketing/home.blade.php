@extends('layouts.marketing')

<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');

    $heroHeadline = \App\Models\SiteSetting::get('hero_headline');
    $heroSubheadline = \App\Models\SiteSetting::get('hero_subheadline');
    $heroMediaType = \App\Models\SiteSetting::get('hero_media_type', 'none');
    $heroImagePath = \App\Models\SiteSetting::get('hero_image_path');
    $heroYoutubeUrl = \App\Models\SiteSetting::get('hero_youtube_url');
    $heroEmbedUrl = $heroMediaType === 'youtube' ? \App\Models\HomepageFeature::youtubeEmbedUrlFrom($heroYoutubeUrl) : null;
    $heroThumbUrl = $heroMediaType === 'youtube' ? \App\Models\HomepageFeature::youtubeThumbnailUrlFrom($heroYoutubeUrl) : null;

    $features = \App\Models\HomepageFeature::query()
        ->where('is_active', true)
        ->orderBy('sort_order')
        ->get();

    $metaDescriptionDefault = 'AffilStack is the all-in-one platform for affiliate marketers: offer research, AI-written content, UGC video, lead tracking, and a built-in affiliate program — everything you need to find an offer, find a buyer, and get paid.';

    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'SoftwareApplication',
        'name' => $siteName,
        'applicationCategory' => 'BusinessApplication',
        'operatingSystem' => 'Web',
        'description' => $metaDescriptionDefault,
        'url' => route('home'),
        'offers' => [
            '@type' => 'Offer',
            'url' => route('registration.pricing'),
        ],
    ];
?>

@section('title', $siteName.' — The all-in-one platform for affiliate marketers')
@section('meta_description', $metaDescriptionDefault)

@section('content')
    <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) !!}</script>

    {{-- Hero --}}
    <section class="max-w-5xl mx-auto px-6 pt-14 pb-16 sm:pt-20 sm:pb-24">
        <div class="grid lg:grid-cols-2 gap-12 items-center">
            <div class="text-center lg:text-left">
                @if ($heroHeadline)
                    <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 leading-tight text-wrap-balance">
                        {{ $heroHeadline }}
                    </h1>
                @else
                    <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 leading-tight text-wrap-balance">
                        Find the offer. Find the buyer. Promote it everywhere —
                        <span class="text-gold-600">in one click.</span>
                    </h1>
                @endif

                <p class="text-ink-600 mt-5 max-w-xl mx-auto lg:mx-0">
                    {{ $heroSubheadline ?: ($siteName.' turns a product name and an affiliate link into a research brief, a blog article, and a full LinkedIn campaign — built for beginners, sharp enough for pros.') }}
                </p>

                <div class="mt-8 flex flex-col sm:flex-row items-center justify-center lg:justify-start gap-3">
                    <a href="{{ route('registration.pricing') }}" class="w-full sm:w-auto rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition text-center">
                        See plans &amp; pricing
                    </a>
                    <a href="{{ route('help.index') }}" class="w-full sm:w-auto rounded-md border border-line px-6 py-3 text-sm font-medium text-navy-900 hover:bg-surface-muted transition text-center">
                        See how it works
                    </a>
                </div>
                <p class="text-xs text-ink-400 mt-4">14-day money-back guarantee on every plan. No free-for-all signups — every account starts with a real plan, so every user you meet on {{ $siteName }} is a real customer.</p>
            </div>

            <div>
                @if ($heroMediaType === 'image' && $heroImagePath)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($heroImagePath) }}"
                        alt="{{ $siteName }}"
                        class="w-full rounded-xl border border-line shadow-sm object-cover aspect-video"
                    >
                @elseif ($heroMediaType === 'youtube' && $heroEmbedUrl)
                    <div
                        class="relative w-full aspect-video rounded-xl overflow-hidden border border-line shadow-sm cursor-pointer group bg-navy-950"
                        data-youtube-facade
                        data-embed="{{ $heroEmbedUrl }}"
                        role="button"
                        tabindex="0"
                        aria-label="Play video"
                    >
                        @if ($heroThumbUrl)
                            <img src="{{ $heroThumbUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                        @endif
                        <div class="absolute inset-0 flex items-center justify-center bg-navy-900/30 group-hover:bg-navy-900/45 transition">
                            <span class="h-16 w-16 rounded-full bg-white/95 flex items-center justify-center text-navy-900 text-2xl shadow">▶</span>
                        </div>
                    </div>
                @else
                    <div class="w-full aspect-video rounded-xl border border-line bg-surface-muted flex items-center justify-center text-ink-400 text-sm">
                        Research → Content → Sales, in minutes.
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- Feature grid --}}
    <section class="max-w-5xl mx-auto px-6 pb-20">
        <div class="text-center max-w-2xl mx-auto mb-10">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-wrap-balance">Everything an affiliate marketer needs, in one place</h2>
            <p class="text-ink-600 mt-3">From your first offer to your first payout — research, content, outreach, tracking, and payments, without stitching together a dozen tools.</p>
        </div>

        @if ($features->isNotEmpty())
            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6 text-sm">
                @foreach ($features as $feature)
                    <div class="border border-line rounded-lg overflow-hidden bg-surface flex flex-col">
                        @if ($feature->media_type === 'image' && $feature->image_path)
                            <img
                                src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($feature->image_path) }}"
                                alt="{{ $feature->title }}"
                                class="w-full aspect-video object-cover"
                                loading="lazy"
                            >
                        @elseif ($feature->media_type === 'youtube' && $feature->youtubeEmbedUrl())
                            <div
                                class="relative w-full aspect-video cursor-pointer group bg-navy-950"
                                data-youtube-facade
                                data-embed="{{ $feature->youtubeEmbedUrl() }}"
                                role="button"
                                tabindex="0"
                                aria-label="Play video for {{ $feature->title }}"
                            >
                                @if ($feature->youtubeThumbnailUrl())
                                    <img src="{{ $feature->youtubeThumbnailUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                                @endif
                                <div class="absolute inset-0 flex items-center justify-center bg-navy-900/30 group-hover:bg-navy-900/45 transition">
                                    <span class="h-11 w-11 rounded-full bg-white/95 flex items-center justify-center text-navy-900 text-lg shadow">▶</span>
                                </div>
                            </div>
                        @endif

                        <div class="p-5 flex-1 flex flex-col">
                            @if ($feature->icon)
                                <div class="text-2xl mb-2" aria-hidden="true">{{ $feature->icon }}</div>
                            @endif
                            <h3 class="font-display font-semibold text-navy-900 mb-1">{{ $feature->title }}</h3>
                            <p class="text-ink-600">{{ $feature->description }}</p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    {{-- How it works --}}
    <section class="bg-surface-muted border-y border-line">
        <div class="max-w-5xl mx-auto px-6 py-16">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-navy-900 text-center text-wrap-balance">How {{ $siteName }} works</h2>
            <div class="grid sm:grid-cols-3 gap-8 mt-10 text-sm text-center">
                <div>
                    <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white font-display font-semibold flex items-center justify-center mb-3">1</div>
                    <h3 class="font-display font-semibold text-navy-900 mb-1">Tell it what you're promoting</h3>
                    <p class="text-ink-600">A product name, a link, or a niche — that's all it needs to get started.</p>
                </div>
                <div>
                    <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white font-display font-semibold flex items-center justify-center mb-3">2</div>
                    <h3 class="font-display font-semibold text-navy-900 mb-1">Get research, content, and UGC video</h3>
                    <p class="text-ink-600">Blog articles, social campaigns, and human-avatar UGC videos, generated and ready to publish.</p>
                </div>
                <div>
                    <div class="mx-auto h-10 w-10 rounded-full bg-navy-900 text-white font-display font-semibold flex items-center justify-center mb-3">3</div>
                    <h3 class="font-display font-semibold text-navy-900 mb-1">Promote, track, and get paid</h3>
                    <p class="text-ink-600">Every click and sale is tracked automatically, with your leads saved in one built-in CRM.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- Who it's for --}}
    <section class="max-w-5xl mx-auto px-6 py-16">
        <div class="grid sm:grid-cols-2 gap-8">
            <div class="border border-line rounded-lg p-6">
                <h2 class="font-display font-semibold text-lg text-navy-900 mb-2">For individual marketers</h2>
                <p class="text-ink-600 text-sm">Run your entire affiliate business solo — research, content, outreach, and payouts — without hiring a team or juggling separate subscriptions.</p>
            </div>
            <div class="border border-line rounded-lg p-6">
                <h2 class="font-display font-semibold text-lg text-navy-900 mb-2">For growing teams</h2>
                <p class="text-ink-600 text-sm">Bring on collaborators, share what's working, and scale your output without losing track of what each campaign earned.</p>
            </div>
        </div>
    </section>

    {{-- Final CTA --}}
    <section class="max-w-5xl mx-auto px-6 pb-20 text-center">
        <div class="rounded-xl bg-navy-900 px-8 py-12 sm:py-16">
            <h2 class="font-display font-semibold text-2xl sm:text-3xl text-white text-wrap-balance">Ready to find your next sale?</h2>
            <p class="text-white/70 mt-3 max-w-lg mx-auto">Pick a plan and be researching your first offer in minutes.</p>
            <a href="{{ route('registration.pricing') }}" class="inline-block mt-6 rounded-md bg-gold-500 text-navy-900 px-6 py-3 text-sm font-semibold hover:bg-gold-400 transition">
                See plans &amp; pricing
            </a>
        </div>
    </section>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-youtube-facade]').forEach(function (el) {
                var play = function () {
                    var iframe = document.createElement('iframe');
                    iframe.src = el.getAttribute('data-embed');
                    iframe.className = 'absolute inset-0 h-full w-full';
                    iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture');
                    iframe.setAttribute('allowfullscreen', '');
                    iframe.setAttribute('frameborder', '0');
                    el.innerHTML = '';
                    el.appendChild(iframe);
                };
                el.addEventListener('click', play, { once: true });
                el.addEventListener('keydown', function (event) {
                    if (event.key === 'Enter' || event.key === ' ') {
                        event.preventDefault();
                        play();
                    }
                }, { once: true });
            });
        });
    </script>
@endsection
