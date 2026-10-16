@extends('layouts.app')

@section('title', 'Connect WordPress')

@php
    use App\Services\WordPress\WordPressSiteService;
    $blocked = $limit === 0 || ($limit > 0 && $used >= $limit && ! $alreadyConnected);
@endphp

@section('content')
    <div class="mx-auto max-w-xl">
        <x-ui.card padding="p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-heroicon-o-globe-alt class="h-6 w-6" /></span>
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">AffilStack Connector</p>
                    <h2 class="font-display text-xl font-semibold text-navy-900">Connect a WordPress site</h2>
                </div>
            </div>

            @if ($error)
                <p class="mt-6 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ \App\Support\Translation\Mark::text($error) }}</p>
                <x-ui.button class="mt-6" variant="secondary" :href="route('wordpress.index')">Back to WordPress Sites</x-ui.button>
            @else
                <div class="mt-6 rounded-xl border border-line p-4">
                    <p class="font-display font-semibold text-navy-900">{{ $siteName !== '' ? $siteName : parse_url($target['site_url'], PHP_URL_HOST) }}</p>
                    <p class="text-sm text-brand-600">{{ $target['site_url'] }}</p>
                </div>

                <p class="mt-5 text-sm text-ink-600">This site will be able to receive your AffilStack blog articles as drafts. It can't see anything else in your account, and you can disconnect it any time.</p>

                @if ($blocked)
                    <p class="mt-5 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        @if ($limit === 0)
                            Your plan doesn't include the WordPress plugin.
                        @else
                            Your plan allows {{ WordPressSiteService::limitLabel($limit) }} and you've connected {{ $used }}. Disconnect a site or upgrade to add this one.
                        @endif
                    </p>
                    <div class="mt-6 flex flex-wrap gap-2">
                        <x-ui.button variant="gold" :href="route('billing.index')" icon="sparkles">See plans</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('wordpress.index')">Manage sites</x-ui.button>
                    </div>
                @else
                    <form method="POST" action="{{ route('wordpress.approve') }}" class="mt-6 flex flex-wrap gap-2">
                        @csrf
                        <input type="hidden" name="site_url" value="{{ $target['site_url'] }}">
                        <input type="hidden" name="rest_url" value="{{ $target['rest_url'] }}">
                        <input type="hidden" name="return_url" value="{{ $target['return_url'] }}">
                        <input type="hidden" name="state" value="{{ $state }}">
                        <input type="hidden" name="site_name" value="{{ $siteName }}">
                        <x-ui.button icon="check">{{ $alreadyConnected ? 'Reconnect this site' : 'Connect this site' }}</x-ui.button>
                        <x-ui.button variant="secondary" :href="route('wordpress.index')">Cancel</x-ui.button>
                    </form>
                @endif
            @endif
        </x-ui.card>
    </div>
@endsection
