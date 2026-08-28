@extends('layouts.app')

@section('title', 'Offer Research')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-ink-600 max-w-lg">Every offer you research becomes the starting point for its blog article and LinkedIn content.</p>
        <a href="{{ route('offers.create') }}" class="text-sm rounded-md bg-navy-900 text-white px-3 py-1.5 hover:bg-navy-800 transition whitespace-nowrap">+ Research a new offer</a>
    </div>

    @if ($offers->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No offers yet. <a href="{{ route('offers.create') }}" class="text-brand-600 hover:text-brand-700 font-medium">Research your first one</a>.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg divide-y divide-line">
            @foreach ($offers as $offer)
                <a href="{{ route('offers.show', $offer) }}" class="flex items-center justify-between px-5 py-3.5 hover:bg-surface-muted transition">
                    <div>
                        <div class="text-sm font-medium text-ink-900">{{ $offer->product_name }}</div>
                        <div class="text-xs text-ink-600">{{ $offer->affiliate_network }} · {{ $offer->created_at->diffForHumans() }}</div>
                    </div>
                    <span class="text-xs font-mono px-2 py-1 rounded bg-surface-muted text-ink-600 capitalize">{{ $offer->status }}</span>
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $offers->links() }}</div>
    @endif
@endsection
