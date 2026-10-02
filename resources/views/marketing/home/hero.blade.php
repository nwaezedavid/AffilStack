@use('App\Support\Homepage\HomepageContent')
@php
    $heroMediaType = \App\Models\SiteSetting::get('hero_media_type', 'none');
    $heroImagePath = \App\Models\SiteSetting::get('hero_image_path');
    $heroYoutubeUrl = \App\Models\SiteSetting::get('hero_youtube_url');
    $heroImageAlt = trim((string) \App\Models\SiteSetting::get('hero_image_alt', '')) ?: HomepageContent::plain(HomepageContent::text('hero_headline'));
    $primaryUrl = HomepageContent::url('home_hero_primary_url', route('registration.pricing'));
    $secondaryUrl = HomepageContent::url('home_hero_secondary_url', '#how-it-works');
    $worksWith = ['googlemaps' => 'Google Maps', 'linkedin' => 'LinkedIn', 'gmail' => 'Gmail', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'x' => 'X', 'pinterest' => 'Pinterest', 'wordpress' => 'WordPress'];
@endphp

<section class="relative isolate overflow-hidden bg-navy-950 text-white" aria-labelledby="hero-heading">
    <div class="absolute inset-0 -z-10" aria-hidden="true">
        <div class="absolute inset-0" style="background: radial-gradient(60% 70% at 15% 10%, rgba(36,82,217,.55) 0%, transparent 60%), radial-gradient(45% 55% at 90% 85%, rgba(201,162,74,.32) 0%, transparent 65%), radial-gradient(40% 40% at 70% 15%, rgba(124,58,237,.25) 0%, transparent 70%);"></div>
        <div class="home-grid-pattern absolute inset-0"></div>
    </div>

    <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 pt-14 pb-20 sm:px-6 sm:pt-20 lg:grid-cols-12 lg:gap-10 lg:px-8 lg:pt-24 lg:pb-28">
        <div class="lg:col-span-6 text-center lg:text-left">
            <p class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3.5 py-1.5 text-[11px] font-medium uppercase tracking-[0.16em] text-gold-300 ring-1 ring-white/15 backdrop-blur font-mono">
                <span class="h-1.5 w-1.5 rounded-full bg-gold-400" aria-hidden="true"></span>
                {{ HomepageContent::text('home_hero_eyebrow') }}
            </p>

            <h1 id="hero-heading" class="mt-6 font-display text-4xl font-semibold leading-[1.05] tracking-tight text-balance sm:text-5xl lg:text-[3.75rem]">
                {{ HomepageContent::highlight(HomepageContent::text('hero_headline'), 'bg-gradient-to-r from-gold-300 via-amber-200 to-gold-400 bg-clip-text text-transparent') }}
            </h1>

            <p class="mx-auto mt-6 max-w-xl text-base leading-relaxed text-white/75 sm:text-lg lg:mx-0">
                {{ HomepageContent::text('hero_subheadline') }}
            </p>

            <div class="mt-9 flex flex-col items-center gap-3 sm:flex-row sm:justify-center lg:justify-start">
                <a href="{{ $primaryUrl }}" class="group inline-flex w-full items-center justify-center gap-2 rounded-full bg-gold-400 px-7 py-3.5 text-sm font-semibold text-navy-950 shadow-[0_12px_32px_-12px_rgba(211,168,84,.9)] transition hover:bg-gold-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950 sm:w-auto">
                    {{ HomepageContent::text('home_hero_primary_label') }}
                    <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" aria-hidden="true" />
                </a>
                <a href="{{ $secondaryUrl }}" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white/10 px-7 py-3.5 text-sm font-semibold text-white ring-1 ring-white/25 backdrop-blur transition hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-white sm:w-auto">
                    <x-heroicon-m-play-circle class="h-5 w-5 text-gold-300" aria-hidden="true" />
                    {{ HomepageContent::text('home_hero_secondary_label') }}
                </a>
            </div>

            @if ($note = HomepageContent::text('home_hero_note'))
                <p class="mt-5 inline-flex items-center gap-2 text-sm text-white/60">
                    <x-heroicon-s-shield-check class="h-4 w-4 text-emerald-400" aria-hidden="true" />
                    {{ $note }}
                </p>
            @endif

            <div class="mt-10 border-t border-white/10 pt-6">
                <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-white/45">Works with the channels you already use</p>
                <ul class="mt-4 flex flex-wrap items-center justify-center gap-x-5 gap-y-3 lg:justify-start" role="list">
                    @foreach ($worksWith as $brand => $label)
                        <li class="text-white/55 transition hover:text-white" title="{{ $label }}">
                            <x-ui.brand-icon :name="$brand" mono class="h-5 w-5" />
                            <span class="sr-only">{{ $label }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="lg:col-span-6">
            @if ($heroMediaType === 'image' && $heroImagePath)
                <div class="relative">
                    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-brand-500/40 via-violet-500/20 to-gold-400/40 blur-2xl" aria-hidden="true"></div>
                    <img src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($heroImagePath) }}" alt="{{ $heroImageAlt }}"
                         class="relative aspect-[16/10] w-full rounded-2xl object-cover shadow-2xl ring-1 ring-white/15" fetchpriority="high">
                </div>
            @elseif ($heroMediaType === 'youtube' && \App\Support\YouTubeUrl::idFrom($heroYoutubeUrl))
                <div class="relative">
                    <div class="absolute -inset-4 rounded-[2rem] bg-gradient-to-tr from-brand-500/40 via-violet-500/20 to-gold-400/40 blur-2xl" aria-hidden="true"></div>
                    <x-youtube-facade :url="$heroYoutubeUrl" :title="HomepageContent::plain(HomepageContent::text('hero_headline'))" class="relative rounded-2xl shadow-2xl ring-1 ring-white/15" />
                </div>
            @else
                @include('marketing.home.partials.product-preview')
            @endif
        </div>
    </div>
</section>
