@extends('layouts.app')

@section('title', 'Network Programs')

@php
    use App\Services\Networks\NetworkRegistry;
    $tabs = ['joined' => 'Joined', 'pending' => 'Waiting for approval', 'available' => 'Not joined'];
@endphp

@section('content')
    <x-ui.page-header
        eyebrow="Affiliate Networks"
        title="Programs"
        subtitle="Programs you've joined become AffilStack products in one click, already using the network's tracked link. For ones you haven't joined, apply on the network; they move to Joined after the next sync."
        icon="squares-2x2"
        tone="brand"
    >
        <x-slot:actions>
            <x-ui.button variant="secondary" :href="route('networks.index')" icon="link">Manage networks</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($connections->isEmpty())
        <div class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center">
            <x-heroicon-o-squares-2x2 class="mx-auto h-10 w-10 text-ink-400" />
            <p class="mt-3 font-display font-semibold text-navy-900">No networks connected yet</p>
            <p class="mx-auto mt-1 max-w-md text-sm text-ink-600">Connect a network to see its programs here.</p>
            <x-ui.button class="mt-4" :href="route('networks.index')" icon="plus">Connect a network</x-ui.button>
        </div>
    @else
        <form method="GET" action="{{ route('networks.programs') }}" class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center">
            <input type="hidden" name="status" value="{{ $status }}">
            <label for="program-network" class="sr-only">Network</label>
            <select id="program-network" name="network" onchange="this.form.submit()" class="rounded-full border border-line bg-white px-3 py-2 text-sm text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">All networks</option>
                @foreach ($connections as $connection)
                    <option value="{{ $connection->network }}" @selected($network?->id === $connection->id)>{{ $connection->label() }}</option>
                @endforeach
            </select>
            <div class="relative flex-1">
                <label for="program-search" class="sr-only">Search programs</label>
                <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                <input id="program-search" type="search" name="q" value="{{ $search }}" placeholder="Search by brand or category" class="w-full rounded-full border border-line bg-white py-2 pl-9 pr-3 text-sm text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            </div>
            <x-ui.button size="sm" variant="secondary">Search</x-ui.button>
        </form>

        <div class="mb-4 flex gap-1 overflow-x-auto border-b border-line" role="tablist">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('networks.programs', array_filter(['status' => $key, 'network' => $network?->network, 'q' => $search ?: null])) }}" role="tab" aria-selected="{{ $status === $key ? 'true' : 'false' }}"
                   class="-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-medium transition {{ $status === $key ? 'border-brand-600 text-navy-900' : 'border-transparent text-ink-400 hover:text-navy-900' }}">
                    {{ $label }} <span class="ml-1 rounded-full bg-surface-muted px-1.5 py-0.5 text-[11px] text-ink-600">{{ number_format($counts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>

        @if ($status === 'available' && $connections->contains(fn ($c) => ! (NetworkRegistry::config($c->network)['marketplace'] ?? false)))
            <p class="mb-4 text-xs text-ink-400">{{ $connections->reject(fn ($c) => NetworkRegistry::config($c->network)['marketplace'] ?? false)->map->label()->implode(', ') }} {{ $connections->reject(fn ($c) => NetworkRegistry::config($c->network)['marketplace'] ?? false)->count() === 1 ? "doesn't" : "don't" }} share {{ $connections->reject(fn ($c) => NetworkRegistry::config($c->network)['marketplace'] ?? false)->count() === 1 ? 'its' : 'their' }} marketplace with other apps. Find programs there, then they'll show under Joined.</p>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-3 lg:col-span-2">
                @forelse ($programs as $program)
                    @php
                        $config = NetworkRegistry::config($program->connection->network);
                        $applyUrl = isset($config['apply_url']) ? str_replace('{id}', rawurlencode($program->external_id), $config['apply_url']) : ($program->meta['invite_url'] ?? $config['dashboard_url']);
                    @endphp
                    <x-ui.card padding="p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start">
                            @if ($program->logo_url)
                                <img src="{{ $program->logo_url }}" alt="" loading="lazy" class="h-11 w-11 shrink-0 rounded-lg border border-line bg-white object-contain p-1">
                            @else
                                <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-surface-muted font-display text-sm font-semibold text-navy-900" aria-hidden="true">{{ mb_strtoupper(mb_substr($program->name, 0, 1)) }}</span>
                            @endif
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-display font-semibold text-navy-900">{{ $program->name }}</p>
                                    <span class="rounded-full bg-surface-muted px-2 py-0.5 text-[11px] text-ink-600">{{ $program->connection->label() }}</span>
                                </div>
                                @if ($program->homepage_url)
                                    <a href="{{ $program->homepage_url }}" target="_blank" rel="noopener nofollow" class="block truncate text-xs text-brand-600 hover:text-brand-700">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($program->homepage_url, '/')) }}</a>
                                @endif
                                <p class="mt-1.5 flex flex-wrap gap-x-3 gap-y-1 text-xs text-ink-400">
                                    @if ($program->commission)<span><span class="text-ink-600">Commission:</span> {{ $program->commission }}</span>@endif
                                    @if ($program->category)<span>{{ $program->category }}</span>@endif
                                    @if ($program->currency)<span>Pays in {{ $program->currency }}</span>@endif
                                    @if ($program->deep_linking)<span>Deep links allowed</span>@endif
                                </p>
                                @if ($program->description)
                                    <p class="mt-2 line-clamp-2 text-xs text-ink-600">{{ $program->description }}</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-wrap gap-2 sm:flex-col sm:items-end">
                                @if ($program->offers->isNotEmpty())
                                    <x-ui.button size="sm" variant="secondary" :href="route('offers.show', $program->offers->first())" icon="check">Open product</x-ui.button>
                                @elseif ($program->isJoined() && $isOwner)
                                    <form method="POST" action="{{ route('networks.programs.import', $program) }}">
                                        @csrf
                                        <x-ui.button size="sm" icon="plus">Add as product</x-ui.button>
                                    </form>
                                @elseif (! $program->isJoined())
                                    <x-ui.button size="sm" variant="secondary" :href="$applyUrl" target="_blank" rel="noopener" icon="arrow-top-right-on-square">{{ $program->status === 'pending' ? 'View on' : 'Apply on' }} {{ $program->connection->label() }}</x-ui.button>
                                @endif
                            </div>
                        </div>
                    </x-ui.card>
                @empty
                    <div class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center text-sm text-ink-600">
                        @if ($search !== '')
                            No programs match "{{ $search }}".
                        @elseif ($connections->every(fn ($c) => $c->programs_synced_at === null))
                            Your programs are being imported. Refresh in a minute.
                        @elseif ($status === 'joined')
                            No joined programs yet. Apply to programs on your network, or add one below.
                        @else
                            Nothing here right now.
                        @endif
                    </div>
                @endforelse

                <div>{{ $programs->links() }}</div>
            </div>

            <div class="space-y-4">
                <x-ui.card title="Adding a program costs research credits" icon="sparkles" padding="p-5">
                    <p class="text-sm text-ink-600">"Add as product" runs the same offer research as adding a product by hand ({{ config('credits.costs.research') }} credits), then every piece of content you create uses the network's tracked link.</p>
                </x-ui.card>

                @if ($isOwner)
                    @foreach ($manualConnections as $connection)
                        @php
                            $adapter = NetworkRegistry::adapter($connection->network);
                        @endphp
                        <x-ui.card title="Add a {{ $connection->label() }} product" icon="plus-circle" padding="p-5">
                            <form method="POST" action="{{ route('networks.programs.add', $connection) }}" class="space-y-3">
                                @csrf
                                <label for="manual-{{ $connection->id }}" class="block text-xs text-ink-600">{{ $adapter->manualProgramLabel() }}</label>
                                <input id="manual-{{ $connection->id }}" name="identifier" required maxlength="300" class="w-full rounded-xl border border-line bg-white px-3 py-2 font-mono text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                                <x-ui.button size="sm" icon="plus">Add</x-ui.button>
                            </form>
                            <p class="mt-3 text-xs text-ink-400">{{ $connection->label() }} doesn't share its marketplace with other apps, so products you've sold before show up on their own and you add new ones here.</p>
                        </x-ui.card>
                    @endforeach
                @endif
            </div>
        </div>
    @endif
@endsection
