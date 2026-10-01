{{-- This product's content for one feature, newest first — also returned
     alone (JSON 'html') to the page's poller while anything is running. --}}
<div data-feature-history data-pending="{{ $pending ? '1' : '0' }}" class="space-y-4">
    @forelse ($generations as $gen)
        @include('dashboard.offers.generations._card')
    @empty
        <x-ui.empty-state :icon="$feature['icon']" title="Nothing created yet">
            Use the form above — your {{ \Illuminate\Support\Str::lower($feature['label']) }} for {{ $offer->product_name }} will appear here, ready to copy.
        </x-ui.empty-state>
    @endforelse
</div>
