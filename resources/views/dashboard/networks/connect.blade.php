@extends('layouts.app')

@section('title', 'Connect '.$config['label'])

@section('content')
    <div class="mb-4">
        <a href="{{ route('networks.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" /> Affiliate Networks</a>
    </div>

    <div class="grid gap-6 lg:grid-cols-5">
        <x-ui.card class="lg:col-span-3" padding="p-6 sm:p-8">
            <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">Connect a network</p>
            <h2 class="mt-1 font-display text-xl font-semibold text-navy-900">{{ $connection?->status === 'error' ? 'Update your '.$config['label'].' keys' : 'Connect '.$config['label'] }}</h2>
            <p class="mt-2 text-sm text-ink-600">{{ $config['label'] }} doesn't offer "Sign in with…" for affiliates, so you'll paste {{ count($config['fields']) > 1 ? 'a few details' : 'one key' }} from your {{ $config['label'] }} account. AffilStack checks {{ count($config['fields']) > 1 ? 'them' : 'it' }} straight away and stores {{ count($config['fields']) > 1 ? 'them' : 'it' }} encrypted. Read-only access is all it needs.</p>

            @if ($blocked)
                <p class="mt-6 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">{{ $blocked }}</p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button variant="gold" :href="route('billing.index')" icon="sparkles">See plans</x-ui.button>
                    <x-ui.button variant="secondary" :href="route('networks.index')">Back</x-ui.button>
                </div>
            @else
                @if ($canApprove)
                    <div class="mt-6 rounded-xl border border-brand-100 bg-brand-50/60 p-4">
                        <p class="text-sm font-semibold text-navy-900">Fastest: approve on Digistore24</p>
                        <p class="mt-1 text-xs text-ink-600">You'll sign in to Digistore24, allow read-only access, and come straight back here, connected. No keys to copy.</p>
                        <form method="POST" action="{{ route('networks.digistore24.approve') }}" class="mt-3">
                            @csrf
                            <x-ui.button icon="arrow-top-right-on-square">Approve on Digistore24</x-ui.button>
                        </form>
                    </div>
                    <p class="mt-6 text-xs font-medium uppercase tracking-wide text-ink-400">Or paste an API key</p>
                @endif

                <form method="POST" action="{{ route('networks.store', $network) }}" class="mt-4 space-y-4">
                    @csrf
                    @foreach ($config['fields'] as $name => $field)
                        <div>
                            <label for="field-{{ $name }}" class="block text-sm font-medium text-navy-900">{{ $field['label'] }}</label>
                            <input id="field-{{ $name }}" name="{{ $name }}" type="{{ ($field['secret'] ?? false) ? 'password' : 'text' }}" value="{{ ($field['secret'] ?? false) ? '' : old($name) }}"
                                   @unless ($field['optional'] ?? false) required @endunless autocomplete="off" spellcheck="false"
                                   class="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2.5 font-mono text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30"
                                   aria-describedby="help-{{ $name }}">
                            <p id="help-{{ $name }}" class="mt-1 text-xs text-ink-400">{{ $field['help'] }}</p>
                            @error($name)
                                <p class="mt-1 text-xs text-rose-700">{{ \App\Support\Translation\Mark::text($message) }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <x-ui.button icon="check">{{ $connection?->isActive() ? 'Save and reconnect' : 'Connect' }}</x-ui.button>
                        <span class="text-xs text-ink-400">AffilStack only reads data. It never changes anything in your {{ $config['label'] }} account.</span>
                    </div>
                </form>
            @endif
        </x-ui.card>

        <div class="lg:col-span-2">
            <x-ui.card title="Where to find {{ count($config['fields']) > 1 ? 'these' : 'it' }}" icon="map" padding="p-5">
                <ol class="space-y-3">
                    @foreach ($config['steps'] as $i => $step)
                        <li class="flex gap-3 text-sm">
                            <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-xs font-semibold text-white">{{ $i + 1 }}</span>
                            <span class="text-ink-600">{{ $step }}</span>
                        </li>
                    @endforeach
                </ol>
                <a href="{{ $config['dashboard_url'] }}" target="_blank" rel="noopener" class="mt-5 inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700">Open {{ $config['label'] }} <x-heroicon-m-arrow-top-right-on-square class="h-4 w-4" /></a>
            </x-ui.card>
        </div>
    </div>
@endsection
