@extends('layouts.app')

@section('title', 'Offer Research')

@section('content')
    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-ink-600 max-w-lg">Every offer you research becomes the starting point for its blog article and LinkedIn content.</p>
        <a href="{{ route('offers.create') }}" class="text-sm rounded-md bg-navy-900 text-white px-3 py-1.5 hover:bg-navy-800 transition whitespace-nowrap">+ Research a new offer</a>
    </div>

    <form method="GET" action="{{ route('offers.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by product name"
               class="flex-1 min-w-[200px] rounded-md border border-line px-3 py-2 text-sm">
        <select name="status" class="rounded-md border border-line px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['researching' => 'Researching', 'ready' => 'Ready', 'archived' => 'Archived'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="text-sm rounded-md border border-line text-ink-900 px-3 py-2 hover:bg-surface-muted transition">Filter</button>
        @if (request('q') || request('status'))
            <a href="{{ route('offers.index') }}" class="text-sm text-ink-500 hover:text-ink-700">Clear</a>
        @endif
    </form>

    @if ($offers->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            @if (request('q') || request('status'))
                No offers match your search.
            @else
                No offers yet. <a href="{{ route('offers.create') }}" class="text-brand-600 hover:text-brand-700 font-medium">Research your first one</a>.
            @endif
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
