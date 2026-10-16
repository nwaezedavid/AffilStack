@extends('layouts.app')

@section('title', 'Connect WordPress')

@push('head')
    <meta http-equiv="refresh" content="0;url={{ $returnUrl }}">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
    <div class="mx-auto max-w-xl">
        <x-ui.card padding="p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-50 text-emerald-600"><x-heroicon-o-check class="h-6 w-6" /></span>
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">AffilStack Connector</p>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Approved. Taking you back to WordPress…</h2>
                </div>
            </div>

            <p class="mt-5 text-sm text-ink-600">WordPress finishes the connection on its side. If nothing happens in a few seconds, use the button below.</p>

            <x-ui.button class="mt-6" :href="$returnUrl" icon="arrow-right">Back to {{ $siteHost }}</x-ui.button>
        </x-ui.card>
    </div>
@endsection
