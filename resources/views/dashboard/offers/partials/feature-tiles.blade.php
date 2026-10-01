{{-- Every built-in feature for this product, as a tile linking to its
     dedicated page. Locked tiles (plan or team-seat) still open the page,
     which explains the upgrade path / who can do it instead. --}}
<section data-tour="feature-tiles" aria-labelledby="feature-tiles-title-{{ $offer->id }}">
    <div class="mb-4 flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-600">Step 3 · Create content</p>
            <h3 id="feature-tiles-title-{{ $offer->id }}" class="font-display text-xl font-semibold text-navy-900">Promote {{ $offer->product_name }} with…</h3>
        </div>
        <p class="text-sm text-ink-600">Each tool opens its own page, already set up for this product.</p>
    </div>

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($tiles as $tile)
            @php($tone = \App\Support\OfferFeatures::tone($tile['tone']))
            <a href="{{ $tile['url'] }}"
               class="group relative flex flex-col overflow-hidden rounded-2xl border border-line bg-gradient-to-br {{ $tone['glow'] }} via-white to-white p-4 ring-1 ring-transparent transition hover:-translate-y-0.5 hover:shadow-lg {{ $tone['ring'] }} focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl shadow-sm {{ $tone['icon'] }}">
                        <x-dynamic-component :component="'heroicon-o-'.$tile['icon']" class="h-6 w-6" />
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <h4 class="font-display font-semibold text-navy-900">{{ $tile['short'] }}</h4>
                            @if ($tile['recommended'])
                                <span class="inline-flex items-center gap-0.5 rounded-full bg-gold-400 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-navy-950">
                                    <x-heroicon-s-star class="h-3 w-3" /> Recommended
                                </span>
                            @elseif ($tile['alternative'])
                                <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink-600 ring-1 ring-line">Good alternative</span>
                            @endif
                        </div>
                        <p class="mt-1 text-sm text-ink-600 leading-snug">{{ $tile['tagline'] }}</p>
                    </div>
                </div>
                <div class="mt-4 flex items-center justify-between gap-2 text-xs">
                    @if ($tile['plan_locked'])
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-1 font-medium text-amber-700 ring-1 ring-amber-200">
                            <x-heroicon-m-lock-closed class="h-3.5 w-3.5" /> Upgrade to unlock
                        </span>
                    @elseif ($tile['seat_locked'])
                        <span class="inline-flex items-center gap-1 rounded-full bg-surface-muted px-2 py-1 font-medium text-ink-600 ring-1 ring-line">
                            <x-heroicon-m-user class="h-3.5 w-3.5" /> Account owner only
                        </span>
                    @elseif ($tile['count'] > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-1 font-medium text-emerald-700 ring-1 ring-emerald-200">
                            <x-heroicon-m-check-circle class="h-3.5 w-3.5" /> {{ $tile['count'] }} created
                        </span>
                    @else
                        <span class="text-ink-400">{{ $tile['cost'] ? 'From '.$tile['cost'].' credits' : 'Free to use' }}</span>
                    @endif
                    <span class="inline-flex items-center gap-1 font-semibold {{ $tone['text'] }}">
                        Open <x-heroicon-m-arrow-right class="h-3.5 w-3.5 transition group-hover:translate-x-0.5" />
                    </span>
                </div>
            </a>
        @endforeach
    </div>
</section>
