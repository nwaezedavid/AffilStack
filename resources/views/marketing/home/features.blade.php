@use('App\Support\Homepage\HomepageContent')

<section id="features" class="relative overflow-hidden bg-gradient-to-b from-surface-muted to-white py-20 sm:py-28" aria-labelledby="features-heading">
    <div class="pointer-events-none absolute -right-40 top-20 h-96 w-96 rounded-full bg-brand-100/60 blur-3xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute -left-40 bottom-10 h-96 w-96 rounded-full bg-amber-100/60 blur-3xl" aria-hidden="true"></div>

    <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
        <div class="mx-auto max-w-2xl text-center" data-reveal>
            <p class="font-mono text-[11px] uppercase tracking-[0.2em] text-violet-600">{{ \App\Support\Homepage\HomepageContent::text('home_features_eyebrow') }}</p>
            <h2 id="features-heading" class="mt-3 font-display text-3xl font-semibold tracking-tight text-navy-900 text-balance sm:text-4xl">
                {{ HomepageContent::highlight(HomepageContent::text('home_features_heading'), 'text-violet-600') }}
            </h2>
            <p class="mt-4 text-base text-ink-600 sm:text-lg">{{ HomepageContent::text('home_features_subheading') }}</p>
        </div>

        <div class="mt-14 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @if ($features->isNotEmpty())
                @foreach ($features as $feature)
                    @php $accent = $accents[$loop->index % count($accents)]; @endphp
                    <article class="group relative flex flex-col overflow-hidden rounded-3xl bg-white ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]" data-reveal>
                        @if ($feature->media_type === 'youtube' && $feature->youtubeEmbedUrl())
                            <x-youtube-facade :url="$feature->youtube_url" :title="$feature->title" play-size="sm" class="z-10" />
                        @elseif ($featureImage = $feature->imageUrl())
                            <div class="aspect-[16/10] overflow-hidden border-b border-line bg-surface-muted">
                                <img src="{{ $featureImage }}" alt="{{ $feature->title }}" class="h-full w-full object-cover object-top transition duration-700 group-hover:scale-[1.03] motion-reduce:transition-none" loading="lazy" decoding="async">
                            </div>
                        @endif
                        <div class="flex flex-1 flex-col p-6">
                            <span class="flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accent['tile'] }}">
                                @include('marketing.home.partials.icon', ['icon' => $feature->icon, 'class' => 'h-6 w-6'])
                            </span>
                            <h3 class="mt-5 font-display text-lg font-semibold text-navy-900">
                                @if ($link = $feature->safeLinkUrl())
                                    <a href="{{ $link }}" class="focus:outline-none after:absolute after:inset-0 after:content-['']">{{ $feature->title }}</a>
                                @else
                                    {{ $feature->title }}
                                @endif
                            </h3>
                            <p class="mt-2 flex-1 text-sm leading-relaxed text-ink-600">{{ $feature->description }}</p>
                            @if ($feature->safeLinkUrl())
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold {{ $accent['text'] }}" aria-hidden="true">{{ \App\Support\Homepage\HomepageContent::text('home_features_link_label') }} <x-heroicon-m-arrow-right class="h-4 w-4 transition group-hover:translate-x-0.5" /></span>
                            @endif
                        </div>
                    </article>
                @endforeach
            @else
                {{-- No admin-configured Homepage Features yet (Content → Homepage Features). --}}
                @foreach ($defaultFeatures as $feature)
                    @php $accent = $accents[$loop->index % count($accents)]; @endphp
                    <article class="group relative overflow-hidden rounded-3xl bg-white p-6 ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-[0_24px_48px_-24px_rgba(11,30,61,.35)]" data-reveal>
                        <div class="absolute -right-10 -top-10 h-32 w-32 rounded-full opacity-0 blur-2xl transition duration-500 group-hover:opacity-100 {{ $accent['soft'] }}" aria-hidden="true"></div>
                        <span class="relative flex h-12 w-12 items-center justify-center rounded-2xl ring-1 {{ $accent['tile'] }}">
                            @include('marketing.home.partials.icon', ['icon' => $feature['icon'], 'class' => 'h-6 w-6'])
                        </span>
                        <h3 class="relative mt-5 font-display text-lg font-semibold text-navy-900">{{ $feature['title'] }}</h3>
                        <p class="relative mt-2 text-sm leading-relaxed text-ink-600">{{ $feature['description'] }}</p>
                    </article>
                @endforeach
            @endif
        </div>

        @if (\Illuminate\Support\Facades\Route::has('features.index'))
            <p class="mt-12 text-center" data-reveal>
                <a href="{{ route('features.index') }}" class="inline-flex items-center gap-2 rounded-full bg-navy-900 px-6 py-3 text-sm font-semibold text-white transition hover:bg-navy-800">
                    {{ \App\Support\Homepage\HomepageContent::text('home_features_all_label') }}
                    <x-heroicon-m-arrow-right class="h-4 w-4 text-gold-300" aria-hidden="true" />
                </a>
            </p>
        @endif
    </div>
</section>
