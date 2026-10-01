{{-- Google Maps keyword chips with Copy / "Open in Google Maps" and a
     "Search in" city switcher. Expects $research (ResearchPresenter). --}}
@php
    $mapsKeywords = $research->mapsKeywords();
    $locations = $research->locations();
    $firstLocation = $locations[0] ?? null;
@endphp
@if ($mapsKeywords !== [])
    <div data-maps-block data-tour="maps-keywords" class="rounded-3xl border border-line bg-white p-5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-white ring-1 ring-line">
                    <x-ui.brand-icon name="googlemaps" class="h-5 w-5" />
                </span>
                <div>
                    <h4 class="font-display font-semibold text-navy-900">Google Maps keywords</h4>
                    <p class="text-sm text-ink-600">Local businesses most likely to buy or refer. Open one in Maps, then save businesses with the Chrome extension's <strong>Add to CRM</strong>.</p>
                </div>
            </div>
        </div>

        @if ($locations !== [])
            <div class="mt-4 flex flex-wrap items-center gap-2" role="radiogroup" aria-label="City to search in">
                <span class="text-xs font-medium text-ink-600">Search in:</span>
                @foreach ($locations as $i => $location)
                    <button type="button" role="radio" data-maps-location="{{ $location }}" aria-checked="{{ $i === 0 ? 'true' : 'false' }}"
                            class="rounded-full px-3 py-1 text-xs font-medium ring-1 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 aria-checked:bg-teal-600 aria-checked:text-white aria-checked:ring-teal-600 bg-white text-ink-900 ring-line hover:bg-teal-50">
                        {{ $location }}
                    </button>
                @endforeach
            </div>
        @endif

        <ul class="mt-4 grid gap-2.5 sm:grid-cols-2">
            @foreach ($mapsKeywords as $item)
                <li class="flex flex-col gap-2 rounded-2xl bg-gradient-to-br from-teal-50 to-white p-3.5 ring-1 ring-teal-100">
                    <div class="flex items-start justify-between gap-2">
                        <p class="font-semibold text-navy-900">{{ $item['keyword'] }}</p>
                        <div class="flex shrink-0 items-center gap-1.5">
                            <x-ui.copy-button :text="$item['keyword']" size="xs" />
                            <a href="{{ \App\Support\ResearchPresenter::mapsUrl($item['keyword'], $firstLocation) }}" target="_blank" rel="noopener"
                               data-maps-keyword="{{ $item['keyword'] }}"
                               class="inline-flex items-center gap-1 rounded-full bg-teal-600 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-teal-700 transition">
                                <x-heroicon-m-map-pin class="h-3.5 w-3.5" /> Open in Google Maps
                            </a>
                        </div>
                    </div>
                    @if ($item['why'] !== '')
                        <p class="text-xs text-ink-600">{{ $item['why'] }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>
@endif
