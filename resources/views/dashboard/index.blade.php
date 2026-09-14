@extends('layouts.app')

@section('title', 'Overview')

@section('content')
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Credits remaining</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ number_format($balance) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Current plan</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $subscription?->plan?->name ?? 'No active plan' }}</div>
            @if ($subscription)
                <div class="text-xs text-ink-600 mt-1">Renews {{ $subscription->current_period_end?->format('M j, Y') }}</div>
            @else
                <a href="{{ route('billing.index') }}" class="text-xs text-brand-600 hover:text-brand-700 font-medium">Choose a plan &rarr;</a>
            @endif
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">CRM contacts saved</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ number_format($contactCount) }}</div>
        </div>
    </div>

    <div class="flex items-center justify-between mb-4">
        <h2 class="font-display font-semibold text-base text-navy-900">Recent offers</h2>
        <a href="{{ route('offers.create') }}" class="text-sm rounded-md bg-navy-900 text-white px-3 py-1.5 hover:bg-navy-800 transition">+ Research a new offer</a>
    </div>

    @if ($offers->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            You haven't researched an offer yet. Give AffilStack a product name, its URL, and the affiliate network it's on —
            it'll tell you who to sell to and how.
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
    @endif
@endsection
