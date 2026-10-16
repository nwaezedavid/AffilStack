@extends('layouts.app')

@section('title', $version->name)

@section('content')
    <div class="mb-4">
        <a href="{{ route('marketplace.browse') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> Marketplace</a>
    </div>

    @if ($isOwner && ! $addon->isListed())
        <div class="mb-4 rounded-xl bg-amber-50 px-4 py-3 text-sm text-amber-900">You're seeing your own add-on before it's approved, so you can try it. Customers can't see it yet.</div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" padding="p-6 sm:p-8">
            <div class="flex items-start gap-4">
                @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-16 w-16 text-lg'])
                <div class="min-w-0">
                    <h2 class="font-display text-2xl font-semibold text-navy-900">{{ $version->name }}</h2>
                    <p class="mt-1 text-sm text-ink-400">by {{ $addon->developer->display_name }} · {{ $addon->typeLabel() }} · {{ $version->categoryLabel() }}</p>
                    <p class="mt-3 text-base text-ink-600">{{ $version->tagline }}</p>
                </div>
            </div>

            @if ($version->screenshotUrls() !== [])
                <div class="mt-6 flex snap-x gap-3 overflow-x-auto pb-2">
                    @foreach ($version->screenshotUrls() as $url)
                        <img src="{{ $url }}" alt="Screenshot of {{ $version->name }}" class="h-48 w-auto shrink-0 snap-start rounded-xl border border-line object-cover" loading="lazy">
                    @endforeach
                </div>
            @endif

            <div class="mt-6">@include('partials.marketplace.description', ['text' => $version->description])</div>
        </x-ui.card>

        <div class="space-y-4">
            <x-ui.card padding="p-5">
                <p class="font-display text-2xl font-semibold text-navy-900">{{ $version->priceLabel() }}</p>

                @if ($install && $install->isUsable() && $install->status !== 'pending_payment')
                    <p class="mt-2 inline-flex items-center gap-1.5 text-sm font-medium text-emerald-700"><x-heroicon-m-check-circle class="h-5 w-5" aria-hidden="true" /> In your account</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <x-ui.button :href="route('addons.show', $install)" icon="cog-6-tooth">Manage</x-ui.button>
                        @if ($addon->isApp() && $version->install_url)
                            <x-ui.button variant="secondary" :href="$version->install_url" icon="arrow-top-right-on-square">Open {{ $version->name }}</x-ui.button>
                        @endif
                    </div>
                @elseif ($addon->isApp())
                    <p class="mt-2 text-sm text-ink-600">Connect it from {{ $version->name }}'s website. You'll come back here to choose what it can access{{ $version->isPaid() && ! $isOwner ? ' and subscribe' : '' }}.</p>
                    <form method="POST" action="{{ route('marketplace.install', $addon) }}" class="mt-4">
                        @csrf
                        <x-ui.button icon="link" class="w-full justify-center">Connect {{ $version->name }}</x-ui.button>
                    </form>
                @elseif ($version->isPaid() && ! $isOwner)
                    <p class="mt-1 text-xs text-ink-400">Billed monthly. Cancel any time and keep it until the end of the month.</p>
                    <form method="POST" action="{{ route('marketplace.subscribe', $addon) }}" class="mt-4 space-y-3">
                        @csrf
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
                    <form method="POST" action="{{ route('marketplace.install', $addon) }}" class="mt-4">
                        @csrf
                        <x-ui.button icon="plus" class="w-full justify-center">Add to my account</x-ui.button>
                    </form>
                @endif
            </x-ui.card>

            @if ($addon->isApp() && $version->scopeDescriptions() !== [])
                <x-ui.card title="What it can access" icon="shield-check" padding="p-5">
                    <ul class="space-y-2 text-sm text-ink-600">
                        @foreach ($version->scopeDescriptions() as $description)
                            <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> {{ $description }}</li>
                        @endforeach
                    </ul>
                </x-ui.card>
            @endif

            <x-ui.card padding="p-5">
                <div class="space-y-1.5 text-sm">
                    @foreach (['website_url' => 'Website', 'support_url' => 'Get support', 'privacy_url' => 'Privacy policy'] as $field => $label)
                        @if ($version->{$field})
                            <a href="{{ $version->{$field} }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-1.5 text-brand-600 hover:text-brand-700">{{ $label }} <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" /></a>
                        @endif
                    @endforeach
                    <p class="pt-2 text-xs text-ink-400">Support for this add-on comes from {{ $addon->developer->display_name }} ({{ $addon->developer->support_email }}).</p>
                </div>
            </x-ui.card>
        </div>
    </div>
@endsection
