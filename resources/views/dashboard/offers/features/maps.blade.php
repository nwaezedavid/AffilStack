@php($mapsList = $research->mapsKeywords())
@if ($mapsList === [])
    <div class="rounded-3xl border border-gold-300/60 bg-gold-300/10 p-5 sm:p-6">
        <h3 class="font-display text-lg font-semibold text-navy-900">Get your Google Maps keywords first</h3>
        <p class="mt-1 text-sm text-ink-600">This product's research doesn't include Google Maps keywords yet. Refresh the research and the AI will list the local businesses most likely to buy, with the cities to start in.</p>
        <form method="POST" action="{{ route('offers.research.retry', $offer) }}" class="mt-4">
            @csrf
            <x-ui.button variant="primary" icon="arrow-path">Refresh research ({{ config('credits.costs.research') }} credits)</x-ui.button>
        </form>
    </div>
@else
    @include('dashboard.offers.partials.maps-keywords')
@endif

@include('dashboard.offers.partials.prospects-panel')

<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <div class="flex items-start gap-3">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tone['soft'] }}"><x-heroicon-o-magnifying-glass-circle class="h-5 w-5" /></span>
        <div>
            <h3 class="font-display font-semibold text-navy-900">No extension? Search right here</h3>
            <p class="text-sm text-ink-600">Our built-in Local Leads search pulls businesses from Google Maps so you can import them to your CRM in bulk.</p>
        </div>
    </div>
    @if (auth()->user()->canUseChannel('google_maps'))
        <form method="GET" action="{{ route('leads.index') }}" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto]">
            <div>
                <label for="lead-niche" class="sr-only">Business type</label>
                <input id="lead-niche" name="niche" required value="{{ $mapsList[0]['keyword'] ?? $offer->suggested_maps_niche }}" placeholder="Business type, e.g. dentists"
                       class="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="lead-location" class="sr-only">City</label>
                <input id="lead-location" name="location" required value="{{ $research->locations()[0] ?? '' }}" placeholder="City, e.g. Lagos, Nigeria"
                       class="w-full rounded-xl border border-line px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <x-ui.button variant="primary" icon="magnifying-glass">Find leads</x-ui.button>
        </form>
    @else
        <p class="mt-4 flex flex-wrap items-center gap-2 text-sm text-ink-600">
            <x-heroicon-m-lock-closed class="h-4 w-4 text-amber-600" /> Built-in search is on higher plans — the Chrome extension works on every plan.
            <a href="{{ route('billing.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Upgrade</a>
        </p>
    @endif
</div>
