@extends('layouts.app')

@section('title', 'My Products')

@section('content')
    <x-ui.page-header title="My Products" eyebrow="Everything you promote" icon="cube"
                      subtitle="Every product you research gets its own hub: the AI research, your prospects, and every tool to promote it.">
        @unless (auth()->user()->isSeat() && ! auth()->user()->hasSharedTeamAccess())
            <x-slot:actions>
                <x-ui.button :href="auth()->user()->isSeat() ? route('offers.create') : route('dashboard').'#research'" variant="gold" icon="plus">Research a new product</x-ui.button>
            </x-slot:actions>
        @endunless
    </x-ui.page-header>

    <form method="GET" action="{{ route('offers.index') }}" class="mb-5 flex flex-col gap-2 rounded-2xl border border-line bg-white p-3 sm:flex-row sm:items-center">
        <div class="relative flex-1">
            <label for="offer-search" class="sr-only">Search products</label>
            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
            <input id="offer-search" type="text" name="q" value="{{ request('q') }}" placeholder="Search by product name"
                   class="w-full rounded-xl border border-line bg-surface-muted py-2 pl-9 pr-3 text-sm focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <label for="offer-status" class="sr-only">Status</label>
        <select id="offer-status" name="status" class="rounded-xl border border-line bg-white px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['ready' => 'Ready', 'queued' => 'Researching', 'failed' => 'Needs a retry', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <div class="flex gap-2">
            <x-ui.button variant="primary" icon="funnel">Filter</x-ui.button>
            @if (request('q') || request('status'))
                <x-ui.button :href="route('offers.index')" variant="ghost">Clear</x-ui.button>
            @endif
        </div>
    </form>

    @if ($offers->isEmpty())
        @if (request('q') || request('status'))
            <x-ui.empty-state icon="magnifying-glass" title="No offers match your search">Try a different name or clear the filters.</x-ui.empty-state>
        @else
            <x-ui.empty-state icon="rocket-launch" title="No products yet" :href="route('dashboard').'#research'" action="Research your first product">
                Paste a product's name, link and affiliate platform on Get Started — the AI does the rest.
            </x-ui.empty-state>
        @endif
    @else
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @foreach ($offers as $offer)
                @php
                    $prospects = $offer->crmLists->sum('contacts_count');
                    $bestKey = \App\Support\OfferFeatures::keyForChannel($offer->recommended_channel);
                    [$statusLabel, $statusPill] = match ($offer->status) {
                        'ready' => ['Ready', 'verified'],
                        'queued' => ['Researching…', 'checking'],
                        'failed' => ['Needs a retry', 'failed'],
                        default => [ucfirst($offer->status), 'not_connected'],
                    };
                    $gradient = ['from-brand-500 to-navy-900', 'from-rose-500 to-violet-700', 'from-teal-500 to-emerald-700', 'from-amber-400 to-orange-600', 'from-sky-500 to-indigo-700', 'from-fuchsia-500 to-rose-600'][$offer->id % 6];
                @endphp
                <article class="group flex min-w-0 flex-col overflow-hidden rounded-3xl border border-line bg-white shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition hover:-translate-y-0.5 hover:shadow-lg">
                    <div class="relative bg-gradient-to-br {{ $gradient }} p-5 text-white">
                        <div class="pointer-events-none absolute -right-6 -top-8 h-28 w-28 rounded-full bg-white/15 blur-xl" aria-hidden="true"></div>
                        <div class="relative flex items-start justify-between gap-3">
                            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-white/20 font-display text-base font-semibold ring-1 ring-white/30">{{ mb_strtoupper(mb_substr($offer->product_name, 0, 2)) }}</span>
                            <x-ui.status-pill :status="$statusPill" :label="$statusLabel" class="bg-white! ring-white/40!" />
                        </div>
                        <h3 class="relative mt-3 truncate font-display text-lg font-semibold">
                            <a href="{{ route('offers.show', $offer) }}" class="focus:outline-none after:absolute after:inset-0">{{ $offer->product_name }}</a>
                        </h3>
                        <p class="relative truncate text-xs text-white/80">{{ $offer->affiliate_network }} · researched {{ $offer->updated_at->format('M j, Y') }}</p>
                    </div>
                    <div class="grid grid-cols-3 divide-x divide-line border-b border-line text-center">
                        <div class="p-3">
                            <p class="font-display text-lg font-semibold text-navy-900 tabular-nums">{{ $prospects }}</p>
                            <p class="text-[11px] text-ink-400">Prospects</p>
                        </div>
                        <div class="p-3">
                            <p class="font-display text-lg font-semibold text-navy-900 tabular-nums">{{ $offer->content_count }}</p>
                            <p class="text-[11px] text-ink-400">Content</p>
                        </div>
                        <div class="p-3">
                            <p class="truncate text-sm font-semibold text-navy-900">{{ $bestKey ? \App\Support\OfferFeatures::get($bestKey)['label'] : '—' }}</p>
                            <p class="text-[11px] text-ink-400">Best channel</p>
                        </div>
                    </div>
                    <div class="relative z-10 flex flex-wrap items-center gap-2 p-4">
                        <x-ui.button :href="route('offers.show', $offer)" variant="primary" size="sm" icon="squares-2x2">Open hub</x-ui.button>
                        @if ($offer->status === 'ready' && $bestKey)
                            <x-ui.button :href="route('offers.feature', [$offer, $bestKey])" variant="secondary" size="sm" icon="sparkles">{{ \App\Support\OfferFeatures::get($bestKey)['label'] }}</x-ui.button>
                        @endif
                        @if ($offer->status === 'failed')
                            <form method="POST" action="{{ route('offers.research.retry', $offer) }}">
                                @csrf
                                <x-ui.button variant="secondary" size="sm" icon="arrow-path">Retry research</x-ui.button>
                            </form>
                        @endif
                        @if ($offer->status === 'ready' && ! auth()->user()->isSeat())
                            <x-ui.button :href="route('offers.feature', [$offer, 'maps'])" variant="ghost" size="sm" icon="map-pin">Find buyers</x-ui.button>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
        <div class="mt-6">{{ $offers->links() }}</div>
    @endif
@endsection
