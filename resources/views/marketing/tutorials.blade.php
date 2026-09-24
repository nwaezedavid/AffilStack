@extends('layouts.marketing')

<?php $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack'); ?>

@section('title', 'Learning Centre — '.$siteName)
@section('meta_description', 'Free video tutorials on how to use '.$siteName.' — offer research, content generation, publishing, and getting paid as an affiliate.')

@section('content')
    <script type="application/ld+json">
        {!! json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'CollectionPage',
            'name' => $siteName.' Learning Centre',
            'description' => 'Free video tutorials on how to use '.$siteName.'.',
            'url' => route('tutorials.index'),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) !!}
    </script>

    <div class="max-w-5xl mx-auto px-6 py-14">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <span class="inline-block text-xs font-semibold tracking-wide uppercase text-gold-600 bg-gold-50 border border-gold-200 rounded-full px-3 py-1 mb-4">Learning Centre</span>
            <h1 class="font-display font-semibold text-3xl sm:text-4xl text-navy-900 text-wrap-balance">Learn {{ $siteName }} in minutes, not hours</h1>
            <p class="text-ink-600 mt-4">Short video walkthroughs of every part of the platform — free to watch, no account required.</p>
        </div>

        @forelse ($groups as $category => $tutorials)
            <div class="mb-14">
                <h2 class="font-display font-semibold text-lg text-navy-900 border-b border-line pb-2 mb-6">{{ $category ?: 'General' }}</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($tutorials as $tutorial)
                        <article class="border border-line rounded-lg overflow-hidden bg-surface flex flex-col">
                            @if ($tutorial->youtubeEmbedUrl())
                                <div
                                    class="relative w-full aspect-video cursor-pointer group bg-navy-950"
                                    data-youtube-facade
                                    data-embed="{{ $tutorial->youtubeEmbedUrl() }}"
                                    role="button"
                                    tabindex="0"
                                    aria-label="Play: {{ $tutorial->title }}"
                                >
                                    @if ($tutorial->youtubeThumbnailUrl())
                                        <img src="{{ $tutorial->youtubeThumbnailUrl() }}" alt="" class="absolute inset-0 h-full w-full object-cover" loading="lazy">
                                    @endif
                                    <div class="absolute inset-0 flex items-center justify-center bg-navy-900/30 group-hover:bg-navy-900/45 transition">
                                        <span class="h-11 w-11 rounded-full bg-white/95 flex items-center justify-center text-navy-900 text-lg shadow">▶</span>
                                    </div>
                                </div>
                            @endif
                            <div class="p-5 flex-1 flex flex-col">
                                <h3 class="font-display font-semibold text-navy-900 mb-1">{{ $tutorial->title }}</h3>
                                @if ($tutorial->description)
                                    <p class="text-sm text-ink-600">{{ $tutorial->description }}</p>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @empty
            <p class="text-sm text-ink-400 text-center">No tutorials published yet — check back soon.</p>
        @endforelse

        <div class="text-center border-t border-line pt-10 mt-4">
            <p class="text-sm text-ink-600">Still have questions?</p>
            <a href="{{ route('help.index') }}" class="text-sm font-medium text-brand-600 underline">Visit Help &amp; FAQ →</a>
        </div>
    </div>

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
