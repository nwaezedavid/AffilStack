@extends('layouts.developer')

@php
    $shown = $working ?? $addon->liveVersion;
    $statusLabels = ['draft' => 'Draft', 'submitted' => 'In review', 'rejected' => 'Changes needed', 'approved' => 'Live', 'superseded' => 'Replaced'];
@endphp

@section('title', $shown?->name ?? 'Add-on')
@section('noindex', '1')

@section('content')
    <a href="{{ route('developer.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> Your add-ons</a>

    <div class="mt-4 flex flex-wrap items-start gap-4">
        @include('partials.marketplace.addon-icon', ['version' => $shown, 'size' => 'h-14 w-14 text-base'])
        <div class="min-w-0 flex-1">
            <h1 class="font-display text-2xl font-semibold text-navy-900">{{ $shown?->name }}</h1>
            <p class="text-sm text-ink-400">{{ $addon->typeLabel() }} · {{ number_format($activeInstalls) }} active {{ \Illuminate\Support\Str::plural('customer', $activeInstalls) }} · ${{ number_format($earned / 100, 2) }} earned</p>
        </div>
        <div class="flex flex-wrap gap-2">
            @if ($addon->isListed())
                <x-ui.button variant="secondary" :href="route('marketplace.show', $addon)" icon="eye">View listing</x-ui.button>
            @endif
            @if (! $working || $working->isEditable())
                <x-ui.button :href="route('developer.addons.edit', $addon)" icon="pencil-square">{{ $working ? 'Edit draft' : 'Make changes' }}</x-ui.button>
            @endif
        </div>
    </div>

    @if ($addon->status === 'suspended')
        <div class="mt-5 rounded-xl bg-rose-50 px-4 py-3 text-sm text-rose-800">This add-on has been suspended by AffilStack and is hidden from the marketplace. Contact support if you think this is a mistake.</div>
    @elseif ($addon->status === 'retired')
        <div class="mt-5 rounded-xl bg-surface px-4 py-3 text-sm text-ink-600 ring-1 ring-line">Retired — no longer in the marketplace.</div>
    @endif

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            @if ($working)
                <x-ui.card title="Version {{ $working->version }} · {{ $statusLabels[$working->status] ?? $working->status }}" icon="document-text" padding="p-5 sm:p-6">
                    @if ($working->status === 'submitted')
                        <p class="text-sm text-ink-600">Submitted {{ $working->submitted_at?->diffForHumans() }}. We'll email you when it has been reviewed.</p>
                        <form method="POST" action="{{ route('developer.addons.withdraw', $addon) }}" class="mt-4">@csrf<x-ui.button variant="secondary" icon="arrow-uturn-left">Withdraw to edit</x-ui.button></form>
                    @else
                        @if ($working->status === 'rejected' && $working->review_note)
                            <div class="mb-4 rounded-xl bg-rose-50 p-4 text-sm text-rose-800">
                                <p class="font-semibold">Reviewer's note</p>
                                <p class="mt-1 whitespace-pre-line">{{ $working->review_note }}</p>
                            </div>
                        @endif
                        @if ($problems !== [])
                            <p class="text-sm font-medium text-navy-900">Before you can submit:</p>
                            <ul class="mt-2 space-y-1.5 text-sm text-ink-600">
                                @foreach ($problems as $problem)
                                    <li class="flex gap-2"><x-heroicon-m-exclamation-circle class="mt-0.5 h-4 w-4 shrink-0 text-amber-500" aria-hidden="true" /> {{ \App\Support\Translation\Mark::text($problem) }}</li>
                                @endforeach
                            </ul>
                            <div class="mt-4"><x-ui.button :href="route('developer.addons.edit', $addon)" icon="pencil-square">Finish the listing</x-ui.button></div>
                        @else
                            <p class="text-sm text-ink-600">Everything's filled in. Try it in your own account, then submit it for review.</p>
                            <div class="mt-4 flex flex-wrap gap-2">
                                <form method="POST" action="{{ route('developer.addons.submit', $addon) }}">@csrf<x-ui.button variant="gold" icon="paper-airplane">Submit for review</x-ui.button></form>
                                <x-ui.button variant="secondary" :href="route('marketplace.addon', $addon)" icon="beaker">Try it in my account</x-ui.button>
                            </div>
                        @endif
                    @endif
                </x-ui.card>
            @elseif ($addon->liveVersion)
                <x-ui.card title="Live: version {{ $addon->liveVersion->version }}" icon="check-badge" padding="p-5 sm:p-6">
                    <p class="text-sm text-ink-600">{{ $addon->liveVersion->tagline }}</p>
                    <p class="mt-3 text-xs text-ink-400">{{ $addon->liveVersion->priceLabel() }} · approved {{ $addon->liveVersion->reviewed_at?->format('M j, Y') }}. Changes go through review as a new version; customers keep the live version until it's approved.</p>
                </x-ui.card>
            @endif

            @if ($addon->isApp())
                <x-ui.card title="Connect with AffilStack credentials" icon="key" padding="p-5 sm:p-6">
                    @if (session('client_secret'))
                        <div class="mb-4 rounded-xl border border-gold-300 bg-gold-300/15 p-4">
                            <p class="text-sm font-semibold text-navy-900">Your client secret — copy it now</p>
                            <p class="mt-1 text-xs text-ink-600">For your security we only show it once. Keep it on your server, never in a browser or mobile app.</p>
                            <div class="mt-2 flex items-center gap-2"><code id="client-secret" class="min-w-0 flex-1 truncate rounded-lg bg-white px-3 py-2 font-mono text-xs ring-1 ring-line">{{ session('client_secret') }}</code><x-ui.copy-button target="client-secret" /></div>
                        </div>
                    @endif
                    <dl class="space-y-3 text-sm">
                        <div>
                            <dt class="text-xs font-medium text-ink-400">Client ID</dt>
                            <dd class="mt-1 flex items-center gap-2"><code id="client-id" class="min-w-0 flex-1 truncate rounded-lg bg-surface-muted px-3 py-2 font-mono text-xs">{{ $addon->client_id }}</code><x-ui.copy-button target="client-id" /></dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium text-ink-400">Webhook signing secret</dt>
                            <dd class="mt-1 flex items-center gap-2"><code id="webhook-secret" class="min-w-0 flex-1 truncate rounded-lg bg-surface-muted px-3 py-2 font-mono text-xs">{{ $addon->webhook_secret }}</code><x-ui.copy-button target="webhook-secret" /></dd>
                        </div>
                        <div class="grid gap-2 text-xs text-ink-600 sm:grid-cols-2">
                            <p>Authorize: <code class="font-mono">{{ route('oauth.authorize') }}</code></p>
                            <p>Token: <code class="font-mono">{{ route('api.oauth.token') }}</code></p>
                        </div>
                    </dl>
                    <form method="POST" action="{{ route('developer.addons.secret', $addon) }}" class="mt-4" onsubmit="return confirm('Create a new client secret? The current one stops working straight away.')">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-brand-600 hover:text-brand-700">Create a new client secret</button>
                    </form>
                    <p class="mt-3 text-xs text-ink-400">See <a href="{{ route('developer.docs', 'connect') }}" class="font-medium text-brand-600">Connect with AffilStack</a> for the full flow.</p>
                </x-ui.card>
            @endif
        </div>

        <div class="space-y-4">
            <x-ui.card title="Versions" icon="clock" padding="p-5">
                <ul class="space-y-2 text-sm">
                    @foreach ($versions as $version)
                        <li class="flex items-center justify-between gap-2">
                            <span class="font-mono text-xs text-navy-900">{{ $version->version }}</span>
                            <span class="text-xs text-ink-600">{{ $statusLabels[$version->status] ?? $version->status }} · {{ ($version->reviewed_at ?? $version->updated_at)->format('M j') }}</span>
                        </li>
                    @endforeach
                </ul>
            </x-ui.card>

            @if (! in_array($addon->status, ['retired', 'suspended'], true) && $addon->liveVersion)
                <x-ui.card padding="p-5">
                    <p class="text-sm text-ink-600">Retiring removes it from the marketplace. Paid customers keep it until the end of the month they paid for; it won't renew.</p>
                    <form method="POST" action="{{ route('developer.addons.retire', $addon) }}" class="mt-3" onsubmit="return confirm('Retire this add-on? This cannot be undone from the portal.')">
                        @csrf
                        <button type="submit" class="text-sm font-medium text-rose-600 hover:text-rose-700">Retire add-on</button>
                    </form>
                </x-ui.card>
            @endif
        </div>
    </div>
@endsection
