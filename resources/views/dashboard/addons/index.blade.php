@extends('layouts.app')

@section('title', 'My Add-ons')

@section('content')
    <x-ui.page-header eyebrow="Connections" title="My Add-ons" subtitle="Everything you've added from the marketplace, and your add-on subscriptions." icon="squares-2x2" tone="navy">
        <x-slot:actions>
            <x-ui.button variant="gold" :href="route('marketplace.browse')" icon="puzzle-piece">Browse add-ons</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($installs->isEmpty())
        <x-ui.empty-state icon="puzzle-piece" title="No add-ons yet" :href="route('marketplace.browse')" action="Browse the marketplace">
            Connect apps, add template packs and copy automation recipes built for AffilStack.
        </x-ui.empty-state>
    @else
        <div class="overflow-hidden rounded-2xl border border-line bg-surface">
            <ul class="divide-y divide-line">
                @foreach ($installs as $install)
                    @php($version = $install->addon->liveVersion ?? $install->addon->versionFor(auth()->user()))
                    <li>
                        <a href="{{ route('addons.show', $install) }}" class="flex items-center gap-4 px-5 py-4 hover:bg-surface-muted sm:px-6">
                            @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-10 w-10 text-sm'])
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-navy-900">{{ $version?->name }}</p>
                                <p class="truncate text-xs text-ink-400">{{ $install->addon->typeLabel() }} · by {{ $install->addon->developer?->display_name }}</p>
                            </div>
                            <div class="hidden text-right text-xs sm:block">
                                @if ($install->status === 'pending_payment')
                                    <span class="rounded-full bg-amber-50 px-2.5 py-1 font-medium text-amber-800">Waiting for payment</span>
                                @elseif ($install->status === 'past_due')
                                    <span class="rounded-full bg-rose-50 px-2.5 py-1 font-medium text-rose-700">Payment needed</span>
                                @elseif ($install->isPaid())
                                    <span class="text-ink-600">{{ $install->cancel_at_period_end ? 'Ends' : 'Renews' }} {{ $install->current_period_end?->format('M j, Y') }}</span>
                                @else
                                    <span class="text-ink-600">Free</span>
                                @endif
                            </div>
                            <x-heroicon-m-chevron-right class="h-5 w-5 text-ink-400" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
@endsection
