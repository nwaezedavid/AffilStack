@extends('layouts.guest')

@section('title', 'Connection stopped · AffilStack')

@section('content')
    <div class="text-center">
        <span class="inline-flex h-12 w-12 items-center justify-center rounded-full bg-rose-50 text-rose-600"><x-heroicon-o-shield-exclamation class="h-6 w-6" aria-hidden="true" /></span>
        <h1 class="mt-4 font-display text-lg font-semibold text-navy-900">We couldn't connect this app</h1>
        <p class="mt-2 text-sm text-ink-600">{{ \App\Support\Translation\Mark::text($message) }}</p>
        <div class="mt-6"><x-ui.button :href="route('marketplace.browse')" variant="secondary">Go to the marketplace</x-ui.button></div>
    </div>
@endsection
