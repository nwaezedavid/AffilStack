@extends('layouts.app')

@section('title', 'Research a new offer')

@section('content')
    <div class="max-w-xl">
        <p class="text-sm text-ink-600 mb-6">
            Give AffiliStack three things — the product name, its official URL, and the affiliate network it's on —
            and it will tell you who the ideal buyer is, exactly where to find them, and which of your seven
            channels to promote it through first. <span class="font-mono text-ink-400">({{ config('credits.costs.research') }} credits)</span>
        </p>

        <form method="POST" action="{{ route('offers.store') }}" class="space-y-4 bg-surface border border-line rounded-lg p-6">
            @csrf
            <div>
                <label for="product_name" class="block text-sm font-medium text-ink-900 mb-1">Product name</label>
                <input id="product_name" name="product_name" value="{{ old('product_name') }}" required
                       placeholder="e.g. Doola LLC Formation"
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="product_url" class="block text-sm font-medium text-ink-900 mb-1">Official product URL</label>
                <input id="product_url" name="product_url" type="url" value="{{ old('product_url') }}" required
                       placeholder="https://..."
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <div>
                <label for="affiliate_network" class="block text-sm font-medium text-ink-900 mb-1">Affiliate network</label>
                <input id="affiliate_network" name="affiliate_network" value="{{ old('affiliate_network') }}" required
                       placeholder="e.g. PartnerStack, Impact, ShareASale"
                       class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
                Run research
            </button>
        </form>
    </div>
@endsection
