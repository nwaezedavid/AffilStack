{{-- A product's research results block — rendered on Get Started (most
     recent product), on the product hub, and by the research status poll.
     While research runs it shows an animated progress state and carries
     data-status-url for the poller (see partials/results-script). --}}
@php
    $context = $context ?? 'dashboard';
    $viewer = auth()->user();
    $isOwnerView = ! $viewer->isSeat();
    $recommendedKey = \App\Support\OfferFeatures::keyForChannel($offer->recommended_channel);
    $mapsKeywords = $research->mapsKeywords();
    $locations = $research->locations();
    $firstLocation = $locations[0] ?? null;
    $linkedin = $research->linkedinKeywords();
    $who = $research->whoNeedsIt();
    $personas = $research->personas();
@endphp
<div id="research-results" data-research-results data-tour="research-results"
     data-status="{{ $offer->status }}"
     data-status-url="{{ route('offers.research.status', [$offer, 'context' => $context]) }}"
     class="space-y-6">

    @if ($context === 'dashboard')
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-600">Step 1 · Your AI research</p>
                <h3 class="font-display text-xl font-semibold text-navy-900 truncate">{{ $offer->product_name }}</h3>
                <p class="text-sm text-ink-600 truncate">{{ $offer->affiliate_network }} · researched {{ $offer->updated_at->diffForHumans() }}</p>
            </div>
            <x-ui.button :href="route('offers.show', $offer)" variant="secondary" icon="squares-2x2" size="sm">Open product hub</x-ui.button>
        </div>
    @endif

    @if ($offer->status === 'queued')
        <div class="relative overflow-hidden rounded-3xl border border-brand-100 bg-gradient-to-br from-brand-50 via-white to-gold-300/10 p-6 sm:p-8" role="status" aria-live="polite">
            <div class="flex flex-col items-center text-center sm:flex-row sm:items-start sm:text-left gap-5">
                <span class="relative inline-flex h-16 w-16 shrink-0 items-center justify-center">
                    <span class="absolute inset-0 animate-ping rounded-full bg-brand-500/20"></span>
                    <span class="relative inline-flex h-16 w-16 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-navy-900 text-white shadow-lg">
                        <x-heroicon-o-cpu-chip class="h-8 w-8 animate-pulse" />
                    </span>
                </span>
                <div class="flex-1">
                    <h4 class="font-display text-lg font-semibold text-navy-900">Our AI is researching {{ $offer->product_name }}…</h4>
                    <p class="mt-1 text-sm text-ink-600">This usually takes under two minutes. You can stay here — the results appear automatically — or leave and we'll notify you when it's ready.</p>
                    <ol class="mt-5 grid gap-2 text-sm sm:grid-cols-2">
                        @foreach (['Reading the product page', 'Working out who needs it', 'Finding where your buyers hang out', 'Building Google Maps & LinkedIn keywords', 'Choosing your best channel & angle'] as $i => $stepLabel)
                            <li class="flex items-center gap-2.5 rounded-xl bg-white/80 px-3 py-2 ring-1 ring-brand-100" style="animation: pulse 2s ease-in-out {{ $i * 0.4 }}s infinite">
                                <span class="inline-flex h-5 w-5 items-center justify-center rounded-full bg-brand-600 text-white">
                                    <x-heroicon-m-sparkles class="h-3 w-3" />
                                </span>
                                <span class="text-ink-900">{{ $stepLabel }}</span>
                            </li>
                        @endforeach
                    </ol>
                    <div class="mt-5 h-1.5 w-full overflow-hidden rounded-full bg-brand-100">
                        <div class="h-full w-1/3 rounded-full bg-gradient-to-r from-brand-500 to-gold-400" style="animation: affilstack-progress 1.8s ease-in-out infinite"></div>
                    </div>
                </div>
            </div>
        </div>
    @elseif ($offer->status === 'failed')
        @php($error = $offer->generations->where('module', 'research')->sortByDesc('id')->first()?->error_message)
        <div class="rounded-3xl border border-red-200 bg-gradient-to-br from-red-50 to-white p-6 sm:p-7" role="alert">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start">
                <span class="inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-red-100 text-red-600">
                    <x-heroicon-o-exclamation-triangle class="h-6 w-6" />
                </span>
                <div class="flex-1">
                    <h4 class="font-display text-lg font-semibold text-navy-900">The research didn't finish</h4>
                    <p class="mt-1 text-sm text-ink-600">{{ $error ?: 'Something went wrong while our AI was researching this product.' }} No credits were taken. Try again — it usually works on the second attempt.</p>
                    <form method="POST" action="{{ route('offers.research.retry', $offer) }}" data-research-retry class="mt-4">
                        @csrf
                        <x-ui.button variant="primary" icon="arrow-path">Try again ({{ config('credits.costs.research') }} credits)</x-ui.button>
                    </form>
                </div>
            </div>
        </div>
    @else
        @if ($research->isLegacy())
            <div class="flex flex-col gap-3 rounded-2xl border border-gold-300/60 bg-gold-300/10 p-4 sm:flex-row sm:items-center sm:justify-between">
                <p class="flex items-start gap-2 text-sm text-ink-900">
                    <x-heroicon-o-sparkles class="h-5 w-5 shrink-0 text-gold-600" />
                    This product was researched before our latest upgrade. Refresh it to get the product overview, buyer personas and Google Maps &amp; LinkedIn keywords.
                </p>
                <form method="POST" action="{{ route('offers.research.retry', $offer) }}" data-research-retry class="shrink-0">
                    @csrf
                    <x-ui.button variant="gold" size="sm" icon="arrow-path">Refresh research ({{ config('credits.costs.research') }})</x-ui.button>
                </form>
            </div>
        @endif

        {{-- Overview + best channel --}}
        <div class="grid gap-4 lg:grid-cols-5">
            <div class="rounded-3xl border border-line bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-6 lg:col-span-3">
                <div class="flex items-center gap-2 text-brand-600">
                    <x-heroicon-o-cube class="h-5 w-5" />
                    <h4 class="font-display font-semibold text-navy-900">What it does</h4>
                </div>
                <p class="mt-3 text-[15px] leading-relaxed text-ink-900">{{ $research->overview() }}</p>
                @if ($research->idealCustomer() && $research->idealCustomer() !== $research->overview())
                    <div class="mt-4 rounded-2xl bg-surface-muted p-4">
                        <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">Your ideal buyer</p>
                        <p class="mt-1 text-sm text-ink-900">{{ $research->idealCustomer() }}</p>
                    </div>
                @endif
            </div>

            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-gold-300 via-gold-400 to-amber-500 p-5 text-navy-950 shadow-lg shadow-gold-400/20 sm:p-6 lg:col-span-2">
                <div class="pointer-events-none absolute -right-8 -bottom-10 h-40 w-40 rounded-full bg-white/25 blur-2xl" aria-hidden="true"></div>
                <p class="relative text-[11px] font-mono uppercase tracking-[0.18em] text-navy-900/70">Best channel to start</p>
                <p class="relative mt-1 flex items-center gap-2 font-display text-2xl font-semibold">
                    <x-heroicon-s-trophy class="h-6 w-6" /> {{ \App\Support\ResearchPresenter::channelLabel($offer->recommended_channel) }}
                </p>
                @if ($research->channelReason())
                    <p class="relative mt-2 text-sm text-navy-950/80">{{ $research->channelReason() }}</p>
                @endif
                @if ($offer->recommended_angle)
                    <div class="relative mt-4 rounded-2xl bg-white/60 p-3.5 ring-1 ring-white/70">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-navy-900/70">Angle to use</p>
                            <x-ui.copy-button :text="$offer->recommended_angle" size="xs" />
                        </div>
                        <p class="mt-1 text-sm font-medium">&ldquo;{{ $offer->recommended_angle }}&rdquo;</p>
                    </div>
                @endif
                <div class="relative mt-4">
                    @if ($offer->recommended_channel === 'google_maps' && $isOwnerView)
                        <a href="{{ route('leads.index', array_filter(['niche' => $offer->suggested_maps_niche, 'location' => $offer->suggested_maps_location])) }}"
                           class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800 transition">
                            Find local leads on Google Maps <x-heroicon-m-arrow-right class="h-4 w-4" />
                        </a>
                    @elseif ($recommendedKey)
                        <a href="{{ route('offers.feature', [$offer, $recommendedKey]) }}"
                           class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-semibold text-white hover:bg-navy-800 transition">
                            Start with {{ \App\Support\OfferFeatures::get($recommendedKey)['label'] }} <x-heroicon-m-arrow-right class="h-4 w-4" />
                        </a>
                    @endif
                </div>
            </div>
        </div>

        {{-- Who needs it + personas --}}
        @if ($who['individuals'] !== [] || $who['businesses'] !== [] || $personas !== [])
            <div class="grid gap-4 lg:grid-cols-2">
                @if ($who['individuals'] !== [] || $who['businesses'] !== [])
                    <div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-2 text-violet-600">
                            <x-heroicon-o-user-group class="h-5 w-5" />
                            <h4 class="font-display font-semibold text-navy-900">Who needs it</h4>
                        </div>
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach (['individuals' => ['Individuals', 'user', 'bg-violet-50 text-violet-700'], 'businesses' => ['Businesses', 'building-office-2', 'bg-sky-50 text-sky-700']] as $segment => [$segmentLabel, $segmentIcon, $segmentTone])
                                @if ($who[$segment] !== [])
                                    <div>
                                        <p class="mb-2 inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-semibold {{ $segmentTone }}">
                                            <x-dynamic-component :component="'heroicon-m-'.$segmentIcon" class="h-3.5 w-3.5" /> {{ $segmentLabel }}
                                        </p>
                                        <ul class="space-y-1.5 text-sm text-ink-900">
                                            @foreach ($who[$segment] as $item)
                                                <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500" /> {{ $item }}</li>
                                            @endforeach
                                        </ul>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                @endif

                @if ($personas !== [])
                    <div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
                        <div class="flex items-center gap-2 text-rose-600">
                            <x-heroicon-o-identification class="h-5 w-5" />
                            <h4 class="font-display font-semibold text-navy-900">Buyer personas</h4>
                        </div>
                        <div class="mt-4 space-y-3">
                            @foreach ($personas as $i => $persona)
                                @php($personaTone = ['from-rose-400 to-pink-600', 'from-violet-400 to-indigo-600', 'from-teal-400 to-emerald-600'][$i % 3])
                                <div class="flex gap-3 rounded-2xl bg-surface-muted p-3.5">
                                    <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-gradient-to-br {{ $personaTone }} font-display text-sm font-semibold text-white">{{ $i + 1 }}</span>
                                    <div>
                                        @if ($persona['name'] !== '')
                                            <p class="font-semibold text-navy-900 text-sm">{{ $persona['name'] }}</p>
                                        @endif
                                        <p class="text-sm text-ink-600">{{ $persona['description'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif

        {{-- Pain points + where to find --}}
        <div class="grid gap-4 lg:grid-cols-2">
            @if ($research->painPoints() !== [])
                <div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
                    <div class="flex items-center gap-2 text-red-500">
                        <x-heroicon-o-fire class="h-5 w-5" />
                        <h4 class="font-display font-semibold text-navy-900">Pain points it solves</h4>
                    </div>
                    <ul class="mt-4 space-y-2">
                        @foreach ($research->painPoints() as $pain)
                            <li class="flex gap-2.5 rounded-xl bg-red-50/60 px-3 py-2 text-sm text-ink-900"><x-heroicon-m-bolt class="mt-0.5 h-4 w-4 shrink-0 text-red-500" /> {{ $pain }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @if ($research->whereToFind() !== [])
                <div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
                    <div class="flex items-center gap-2 text-teal-600">
                        <x-heroicon-o-map class="h-5 w-5" />
                        <h4 class="font-display font-semibold text-navy-900">Where to find your buyers</h4>
                    </div>
                    <ul class="mt-4 space-y-2">
                        @foreach ($research->whereToFind() as $place)
                            <li class="flex gap-2.5 rounded-xl bg-teal-50/60 px-3 py-2 text-sm text-ink-900"><x-heroicon-m-map-pin class="mt-0.5 h-4 w-4 shrink-0 text-teal-600" /> {{ $place }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        @include('dashboard.offers.partials.maps-keywords')

        @include('dashboard.offers.partials.linkedin-keywords')

        @include('dashboard.offers.partials.prospects-panel')

        @include('dashboard.offers.partials.feature-tiles')
    @endif
</div>
