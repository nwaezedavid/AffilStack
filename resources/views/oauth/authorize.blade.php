@extends('layouts.guest')

@section('title', 'Connect '.$version->name.' · AffilStack')

@section('content')
    <div class="flex items-center justify-center gap-3">
        @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-14 w-14 text-base'])
        <x-heroicon-o-arrows-right-left class="h-5 w-5 text-ink-400" aria-hidden="true" />
        @if ($brandIcon = \App\Support\Brand::iconUrl())
            <img src="{{ $brandIcon }}" alt="{{ \App\Support\Brand::name() }}" class="h-14 w-14 rounded-xl object-contain">
        @else
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-navy-900 font-display text-base font-semibold text-gold-400" aria-hidden="true">{{ mb_strtoupper(mb_substr(\App\Support\Brand::name(), 0, 2)) }}</span>
        @endif
    </div>

    <h1 class="mt-5 text-center font-display text-xl font-semibold text-navy-900">Connect {{ $version->name }} to your AffilStack account</h1>
    <p class="mt-1 text-center text-sm text-ink-400">by {{ $addon->developer?->display_name }} · signed in as {{ auth()->user()->email }}</p>

    @if ($testing)
        <p class="mt-4 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900">Test mode: this app isn't approved yet. Only you, its developer, can connect it.</p>
    @endif

    @if ($mustPay)
        <div class="mt-6 rounded-xl border border-line bg-surface-muted p-4 text-sm text-ink-600">
            <p><span class="font-semibold text-navy-900">{{ $version->name }}</span> costs <span class="font-semibold text-navy-900">{{ $version->priceLabel() }}</span>, billed by AffilStack. Subscribe first, then you'll come back here to connect it.</p>
        </div>
        <form method="POST" action="{{ route('marketplace.subscribe', $addon) }}" class="mt-4 space-y-3">
            @csrf
            <input type="hidden" name="return" value="{{ route('oauth.authorize', $params) }}">
            @if (count($gateways) > 1)
                <label for="gateway" class="block text-xs font-medium text-ink-600">Pay with</label>
                <select id="gateway" name="gateway" class="w-full rounded-xl border border-line bg-white px-3 py-2.5 text-sm">
                    @foreach ($gateways as $gateway)
                        <option value="{{ $gateway->key() }}">{{ $gateway->label() }}</option>
                    @endforeach
                </select>
            @endif
            <x-ui.button variant="gold" icon="credit-card" class="w-full justify-center">Subscribe — {{ $version->priceLabel() }}</x-ui.button>
        </form>
    @else
        <div class="mt-6">
            <p class="text-sm font-semibold text-navy-900">{{ $version->name }} will be able to:</p>
            <ul class="mt-3 space-y-2 text-sm text-ink-600">
                @foreach ($scopes as $description)
                    <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> {{ $description }}</li>
                @endforeach
            </ul>
            <p class="mt-4 text-xs text-ink-400">It can't see your password or payment details. You can disconnect it at any time from My Add-ons.@if ($version->privacy_url) Read its <a href="{{ $version->privacy_url }}" target="_blank" rel="noopener nofollow" class="font-medium text-brand-600">privacy policy</a>.@endif</p>
        </div>

        <div class="mt-6 grid grid-cols-2 gap-3">
            <form method="POST" action="{{ route('oauth.deny') }}">
                @csrf
                @foreach ($params as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <x-ui.button variant="secondary" class="w-full justify-center">Cancel</x-ui.button>
            </form>
            <form method="POST" action="{{ route('oauth.approve') }}">
                @csrf
                @foreach ($params as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <x-ui.button class="w-full justify-center">Allow</x-ui.button>
            </form>
        </div>
    @endif

    <p class="mt-6 text-center text-xs text-ink-400">You'll be sent back to {{ parse_url($params['redirect_uri'], PHP_URL_HOST) }}.</p>
@endsection
