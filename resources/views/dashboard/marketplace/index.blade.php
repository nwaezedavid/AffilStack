@extends('layouts.app')

@section('title', 'Add-on Marketplace')

@section('content')
    <x-ui.page-header
        eyebrow="Connections"
        title="Add-on Marketplace"
        subtitle="Apps that connect to your account, template packs and automation recipes from independent developers — each one reviewed by AffilStack."
        icon="puzzle-piece"
        tone="navy"
    >
        <x-slot:actions>
            <x-ui.button variant="gold" :href="route('addons.index')" icon="squares-2x2">My add-ons</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @include('partials.marketplace.filters', ['action' => route('marketplace.browse'), 'types' => $types, 'categories' => $categories])

    @if ($addons->isEmpty())
        <x-ui.empty-state class="mt-6" icon="puzzle-piece" :title="request()->hasAny(['q', 'type', 'category']) ? 'No add-ons match your search' : 'No add-ons yet'">
            New add-ons appear here as soon as our team approves them.
        </x-ui.empty-state>
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($addons as $addon)
                @include('partials.marketplace.addon-card', ['addon' => $addon, 'href' => route('marketplace.addon', $addon), 'installed' => $installs->has($addon->id)])
            @endforeach
        </div>
        <div class="mt-6">{{ $addons->links() }}</div>
    @endif
@endsection
