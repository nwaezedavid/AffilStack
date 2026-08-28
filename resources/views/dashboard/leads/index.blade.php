@extends('layouts.app')

@section('title', 'Local Leads')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">Search Google Maps for local businesses matching a niche, then import the ones worth reaching out to straight into your CRM.</p>

    @if (! auth()->user()->canUseChannel('google_maps'))
        <div class="bg-surface border border-line rounded-lg p-5 mb-6">
            <p class="text-sm text-ink-600 mb-2">Local lead-finding isn't included in your current plan.</p>
            <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 underline">Upgrade to unlock it &rarr;</a>
        </div>
    @else
        <form method="POST" action="{{ route('leads.search') }}" class="bg-surface border border-line rounded-lg p-5 mb-6 grid sm:grid-cols-[1fr_1fr_auto] gap-3 items-end">
            @csrf
            <div>
                <label for="niche" class="block text-xs text-ink-600 mb-1">Niche</label>
                <input id="niche" name="niche" value="{{ old('niche', $niche) }}" required placeholder="e.g. dentists, coffee shops" class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="location" class="block text-xs text-ink-600 mb-1">Location</label>
                <input id="location" name="location" value="{{ old('location', $location) }}" required placeholder="e.g. Austin, TX" class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <button class="rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2 hover:bg-navy-800 transition whitespace-nowrap">Search</button>
        </form>

        @if (! empty($results))
            <div class="bg-surface border border-line rounded-lg divide-y divide-line-soft">
                @foreach ($results as $place)
                    @php $isImported = in_array($place['place_id'], $importedPlaceIds ?? [], true); @endphp
                    <div class="p-4 flex items-start justify-between gap-4 flex-wrap">
                        <div>
                            <div class="flex items-center gap-2 flex-wrap">
                                <span class="text-sm font-medium text-ink-900">{{ $place['name'] }}</span>
                                @if ($place['type'])
                                    <span class="text-xs font-mono uppercase text-ink-400">{{ $place['type'] }}</span>
                                @endif
                                @if ($place['rating'])
                                    <span class="text-xs text-ink-600">&#9733; {{ $place['rating'] }} ({{ $place['ratings_total'] }})</span>
                                @endif
                            </div>
                            <div class="text-xs text-ink-600 mt-0.5">{{ $place['address'] }}</div>
                            <a href="{{ $place['maps_url'] }}" target="_blank" rel="noopener" class="text-xs text-brand-600 hover:text-brand-700">View on Google Maps</a>
                        </div>
                        <div>
                            @if ($isImported)
                                <span class="text-xs text-ink-400 font-mono uppercase">Already in CRM</span>
                            @else
                                <form method="POST" action="{{ route('leads.import') }}">
                                    @csrf
                                    <input type="hidden" name="place_id" value="{{ $place['place_id'] }}">
                                    <input type="hidden" name="name" value="{{ $place['name'] }}">
                                    <input type="hidden" name="address" value="{{ $place['address'] }}">
                                    <input type="hidden" name="type" value="{{ $place['type'] }}">
                                    <button class="rounded-md border border-line text-ink-900 text-xs px-2.5 py-1.5 hover:bg-surface-muted transition whitespace-nowrap">Add to CRM</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @elseif ($niche || $location)
            <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
                No businesses found for that search — try a broader niche or location.
            </div>
        @endif
    @endif
@endsection
