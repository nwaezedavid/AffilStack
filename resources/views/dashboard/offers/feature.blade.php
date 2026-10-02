@extends('layouts.app')

@section('title', $feature['label'].' · '.$offer->product_name)

@section('content')
    @php
        $completed = $generations->where('status', 'completed');
        $isRecommended = \App\Support\OfferFeatures::keyForChannel($offer->recommended_channel) === $featureKey;
    @endphp

    <nav aria-label="Breadcrumb" class="mb-3 flex flex-wrap items-center gap-1.5 text-xs text-ink-600">
        @if (! auth()->user()->isSeat() || auth()->user()->hasSharedTeamAccess())
            <a href="{{ route('offers.index') }}" class="hover:text-brand-600">My Products</a>
            <x-heroicon-m-chevron-right class="h-3.5 w-3.5 text-ink-400" />
        @endif
        <a href="{{ route('offers.show', $offer) }}" class="max-w-[12rem] truncate hover:text-brand-600">{{ $offer->product_name }}</a>
        <x-heroicon-m-chevron-right class="h-3.5 w-3.5 text-ink-400" />
        <span class="font-medium text-navy-900">{{ $feature['label'] }}</span>
    </nav>

    <x-ui.page-header :title="$feature['label'].' for '.$offer->product_name" :eyebrow="$isRecommended ? 'Recommended for this product' : 'Built-in feature'"
                      :subtitle="$feature['description']" :icon="$feature['icon']" :tone="$tone['hero']">
        <x-slot:actions>
            <x-ui.button :href="route('offers.show', $offer)" variant="light" icon="arrow-left">Product hub</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Feature switcher --}}
    <nav aria-label="Other tools for this product" class="-mx-4 mb-6 overflow-x-auto px-4 sm:mx-0 sm:px-0">
        <ul class="flex min-w-max gap-2 pb-1">
            @foreach ($tiles as $tile)
                @php($tileTone = \App\Support\OfferFeatures::tone($tile['tone']))
                <li>
                    <a href="{{ $tile['url'] }}" @if ($tile['key'] === $featureKey) aria-current="page" @endif
                       @class([
                           'inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium ring-1 transition',
                           'bg-navy-900 text-white ring-navy-900' => $tile['key'] === $featureKey,
                           'bg-white text-ink-900 ring-line hover:ring-brand-500' => $tile['key'] !== $featureKey,
                       ])>
                        <x-dynamic-component :component="'heroicon-m-'.$tile['icon']" @class(['h-3.5 w-3.5', $tileTone['text'] => $tile['key'] !== $featureKey]) />
                        {{ $tile['label'] }}
                        @if ($tile['plan_locked'] || $tile['seat_locked'])
                            <x-heroicon-m-lock-closed class="h-3 w-3 opacity-60" />
                        @endif
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($planLocked)
                <div class="relative overflow-hidden rounded-3xl border border-amber-200 bg-gradient-to-br from-amber-50 via-white to-gold-300/20 p-6 sm:p-8">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-amber-100 text-amber-700"><x-heroicon-o-lock-closed class="h-6 w-6" /></span>
                    <h3 class="mt-4 font-display text-xl font-semibold text-navy-900">{{ $feature['label'] }} isn't included in your current plan</h3>
                    <p class="mt-1 text-sm text-ink-600">Upgrade to unlock it — your research for {{ $offer->product_name }} is already done, so you can start the moment you upgrade.</p>
                    <div class="mt-5 flex flex-wrap gap-2">
                        @if (auth()->user()->isSeat())
                            <p class="text-sm text-ink-600">Ask your account owner to upgrade the plan.</p>
                        @else
                            <x-ui.button :href="route('billing.index')" variant="gold" icon="sparkles">See plans & upgrade</x-ui.button>
                        @endif
                        <x-ui.button :href="route('offers.show', $offer)" variant="secondary">Try another tool</x-ui.button>
                    </div>
                </div>
            @elseif ($seatLocked)
                <div class="rounded-3xl border border-line bg-white p-6 sm:p-8">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-surface-muted text-ink-600"><x-heroicon-o-user class="h-6 w-6" /></span>
                    <h3 class="mt-4 font-display text-xl font-semibold text-navy-900">Only the account owner can use {{ $feature['label'] }}</h3>
                    <p class="mt-1 text-sm text-ink-600">It works with the account's CRM contacts, which team seats don't have access to. Ask the account owner to run it — everything else for this product is open to you.</p>
                    <div class="mt-5"><x-ui.button :href="route('offers.show', $offer)" variant="primary" icon="arrow-left">Back to the product hub</x-ui.button></div>
                </div>
            @else
                <x-ui.feature-intro :steps="$feature['steps']" :open="$generations->isEmpty()" title="How {{ $feature['label'] }} works">
                    {{ $feature['tagline'] }}
                </x-ui.feature-intro>

                @include('dashboard.offers.features.'.$featureKey)

                @if ($completed->isNotEmpty())
                    @include('dashboard.offers.features._next')
                @endif
            @endif

            @unless (in_array($featureKey, ['maps', 'localize'], true) && $generations->isEmpty())
                <section aria-labelledby="history-title">
                    <div class="mb-3 flex items-end justify-between">
                        <h3 id="history-title" class="font-display text-lg font-semibold text-navy-900">Your {{ $feature['label'] }} history</h3>
                        <span class="text-xs text-ink-400">{{ $completed->count() }} ready</span>
                    </div>
                    <div data-history-slot data-poll-url="{{ route('offers.feature', [$offer, $featureKey, 'fragment' => 'history']) }}">
                        @include('dashboard.offers.features._history')
                    </div>
                </section>
            @endunless
        </div>

        <aside class="space-y-4">
            <div class="rounded-3xl bg-gradient-to-br {{ $tone['glow'] }} via-white to-white p-5 ring-1 ring-line">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl {{ $tone['icon'] }}">
                    <x-dynamic-component :component="'heroicon-o-'.$feature['icon']" class="h-6 w-6" />
                </span>
                <p class="mt-3 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">What you get</p>
                <p class="mt-1 text-sm font-medium text-navy-900">{{ $feature['outcome'] }}</p>
                @if ($feature['cost_keys'] !== [])
                    <ul class="mt-4 space-y-1.5 border-t border-line pt-3 text-xs">
                        @foreach ($feature['cost_keys'] as $costKey)
                            <li class="flex items-center justify-between gap-2">
                                <span class="text-ink-600">{{ \Illuminate\Support\Str::headline(str_replace(['linkedin_', 'youtube_', 'ugc_'], '', $costKey)) }}</span>
                                <span class="inline-flex items-center gap-1 font-semibold text-navy-900"><x-heroicon-m-bolt class="h-3.5 w-3.5 text-gold-500" />{{ config('credits.costs.'.$costKey) }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-3 text-xs text-ink-400">You have <strong class="text-navy-900">{{ number_format(auth()->user()->billableUser()->credits_balance) }}</strong> credits.@unless (auth()->user()->isSeat()) <a href="{{ route('credit-topups.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Top up</a>@endunless</p>
            </div>

            @if ($offer->recommended_angle)
                <div class="rounded-3xl border border-gold-300/60 bg-gold-300/10 p-5">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-gold-600">Your winning angle</p>
                        <x-ui.copy-button :text="$offer->recommended_angle" size="xs" />
                    </div>
                    <p class="mt-1 text-sm font-medium text-navy-900">&ldquo;{{ $offer->recommended_angle }}&rdquo;</p>
                    <p class="mt-2 text-xs text-ink-600">The AI already writes with this angle — keep it consistent across every channel.</p>
                </div>
            @endif

            @if ($research->painPoints() !== [])
                <div class="rounded-3xl border border-line bg-white p-5">
                    <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">Pain points to speak to</p>
                    <ul class="mt-2 space-y-1.5 text-sm text-ink-900">
                        @foreach (array_slice($research->painPoints(), 0, 4) as $pain)
                            <li class="flex gap-2"><x-heroicon-m-bolt class="mt-0.5 h-4 w-4 shrink-0 text-red-500" /> {{ $pain }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </aside>
    </div>
@endsection

@push('scripts')
    <x-ui.copy-script />
    <script>
        (function () {
            const slot = document.querySelector('[data-history-slot]');
            if (!slot) {
                return;
            }
            let wasPending = !!slot.querySelector('[data-feature-history][data-pending="1"]');
            function poll() {
                fetch(slot.dataset.pollUrl, { headers: { Accept: 'application/json' } })
                    .then(function (response) { return response.ok ? response.json() : null; })
                    .then(function (data) {
                        if (!data) {
                            setTimeout(poll, 10000);
                            return;
                        }
                        if (!data.pending && wasPending) {
                            // Something finished: reload so the steps above
                            // (which depend on what exists) update too.
                            window.location.reload();
                            return;
                        }
                        slot.innerHTML = data.html;
                        wasPending = data.pending;
                        if (data.pending) {
                            setTimeout(poll, 5000);
                        }
                    })
                    .catch(function () { setTimeout(poll, 10000); });
            }
            if (wasPending) {
                setTimeout(poll, 5000);
            }
        })();
    </script>
@endpush
