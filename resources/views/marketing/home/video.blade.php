@use('App\Support\Homepage\HomepageContent')
@php
    $videoUrl = \App\Models\SiteSetting::get('home_video_url');
    $posterPath = \App\Models\SiteSetting::get('home_video_poster_path');
    $poster = $posterPath ? \Illuminate\Support\Facades\Storage::disk('public')->url($posterPath) : null;
    $posterAlt = trim((string) \App\Models\SiteSetting::get('home_video_poster_alt', ''));
    $points = array_slice(array_values(array_filter(array_map('trim', preg_split('/\R/', HomepageContent::text('home_video_points')) ?: []))), 0, 3);
@endphp

{{-- "See AffilStack in action" — appears as soon as a YouTube link is saved
     in Content → Homepage → Product video. --}}
@if (\App\Support\YouTubeUrl::idFrom($videoUrl))
    <section id="see-it-in-action" class="relative isolate overflow-hidden bg-navy-950 py-20 text-white sm:py-28" aria-labelledby="video-heading">
        <div class="absolute inset-0 -z-10" aria-hidden="true" style="background: radial-gradient(50% 60% at 85% 30%, rgba(36,82,217,.45) 0%, transparent 65%), radial-gradient(40% 50% at 10% 90%, rgba(201,162,74,.25) 0%, transparent 70%);"></div>
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-4 sm:px-6 lg:grid-cols-12 lg:px-8">
            <div class="lg:col-span-5" data-reveal>
                <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-gold-300">Product tour</p>
                <h2 id="video-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-balance sm:text-4xl">
                    {{ HomepageContent::highlight(HomepageContent::text('home_video_heading'), 'text-gold-300') }}
                </h2>
                <p class="mt-4 text-base text-white/70 sm:text-lg">{{ HomepageContent::text('home_video_subheading') }}</p>
                @if ($points)
                    <ul class="mt-8 space-y-3" role="list">
                        @foreach ($points as $point)
                            <li class="flex items-start gap-3 text-sm text-white/85">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-gold-400 text-navy-950"><x-heroicon-m-check class="h-3.5 w-3.5" aria-hidden="true" /></span>
                                {{ $point }}
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
            <div class="lg:col-span-7" data-reveal>
                <div class="relative">
                    <div class="absolute -inset-3 rounded-[2rem] bg-gradient-to-tr from-gold-400/50 via-brand-500/30 to-violet-500/40 blur-2xl" aria-hidden="true"></div>
                    <x-youtube-facade :url="$videoUrl" :poster="$poster" :poster-alt="$posterAlt" :title="HomepageContent::plain(HomepageContent::text('home_video_heading'))" class="relative rounded-3xl shadow-2xl ring-1 ring-white/20" />
                </div>
            </div>
        </div>
    </section>
@endif
