@extends('layouts.app')

@section('title', 'Affiliate Networks')

@php
    use App\Services\Networks\NetworkConnectionService;
    $included = $limit !== 0;
    $atLimit = $limit > 0 && $used >= $limit;
    $monograms = ['impact' => ['IM', 'bg-rose-600'], 'partnerstack' => ['PS', 'bg-indigo-600'], 'awin' => ['AW', 'bg-orange-500'], 'cj' => ['CJ', 'bg-emerald-700'], 'clickbank' => ['CB', 'bg-amber-500'], 'digistore24' => ['DS', 'bg-sky-600']];
@endphp

@section('content')
    <x-ui.page-header
        eyebrow="Connections"
        title="Affiliate Networks"
        subtitle="Connect your network accounts and AffilStack imports your commissions every day, turns programs you've joined into products with the network's tracked link, and traces each sale back to the post that made it."
        icon="link"
        tone="brand"
    >
        <x-slot:actions>
            @if ($connections->isNotEmpty())
                <x-ui.button variant="gold" :href="route('networks.programs')" icon="squares-2x2">Browse programs</x-ui.button>
            @elseif (! $included)
                <x-ui.button variant="gold" :href="route('billing.index')" icon="sparkles">Upgrade to connect</x-ui.button>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    @unless ($included)
        <div class="mb-6 rounded-2xl border border-gold-300/60 bg-gold-300/15 p-5 text-sm text-navy-900">
            <p class="font-semibold">Your plan doesn't include affiliate network connections.</p>
            <p class="mt-1 text-ink-600">Upgrade to import commissions automatically and get tracked links for every product. You can still add earnings by hand or by CSV on the Earnings page.</p>
        </div>
    @endunless

    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <h2 class="font-display text-base font-semibold text-navy-900">Networks</h2>
        <span class="text-xs text-ink-400">{{ $used }} connected · {{ NetworkConnectionService::limitLabel($limit) }} on your plan</span>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ($networks as $key => $network)
            @php
                $connection = $connections->get($key);
                [$letters, $color] = $monograms[$key] ?? [strtoupper(substr($key, 0, 2)), 'bg-navy-900'];
            @endphp
            <x-ui.card padding="flex flex-1 flex-col p-5" class="flex flex-col">
                <div class="flex items-start gap-3">
                    <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl font-mono text-sm font-semibold text-white {{ $color }}" aria-hidden="true">{{ $letters }}</span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <p class="font-display font-semibold text-navy-900">{{ $network['label'] }}</p>
                            @if ($connection?->status === 'connected')
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[11px] font-semibold text-emerald-800"><span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Connected</span>
                            @elseif ($connection?->status === 'error')
                                <span class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-800">Needs attention</span>
                            @elseif ($connection?->status === 'pending')
                                <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800">Waiting for approval</span>
                            @endif
                        </div>
                        <p class="mt-0.5 text-xs text-ink-400">{{ $network['tagline'] }}</p>
                    </div>
                </div>

                @if ($connection && $connection->isActive())
                    <dl class="mt-4 grid grid-cols-2 gap-x-4 gap-y-2 text-xs">
                        <div class="col-span-2 min-w-0">
                            <dt class="text-ink-400">Account</dt>
                            <dd class="truncate font-medium text-navy-900">{{ $connection->account_name ?: $connection->account_id }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-400">Programs joined</dt>
                            <dd class="font-medium text-navy-900">{{ number_format($connection->joined_count) }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-400">Last sync</dt>
                            <dd class="font-medium text-navy-900">{{ $connection->last_synced_at ? $connection->last_synced_at->diffForHumans() : ($connection->status === 'error' ? 'Not yet' : 'Running now…') }}</dd>
                        </div>
                        @if ($connection->last_synced_at && ($connection->last_sync_new || $connection->last_sync_updated))
                            <div class="col-span-2 text-ink-600">{{ $connection->last_sync_new }} new and {{ $connection->last_sync_updated }} updated {{ \Illuminate\Support\Str::plural('commission', $connection->last_sync_new + $connection->last_sync_updated) }} in that sync.</div>
                        @endif
                    </dl>

                    @if ($connection->last_error)
                        <p class="mt-3 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700">{{ $connection->last_error }}</p>
                    @endif

                    @if ($isOwner)
                        <div class="mt-auto flex flex-wrap gap-2 pt-4">
                            <form method="POST" action="{{ route('networks.sync', $connection) }}">
                                @csrf
                                <x-ui.button size="sm" variant="secondary" icon="arrow-path">Sync now</x-ui.button>
                            </form>
                            <x-ui.button size="sm" variant="secondary" :href="route('networks.programs', ['network' => $key])" icon="squares-2x2">Programs</x-ui.button>
                            @if ($connection->status === 'error')
                                <x-ui.button size="sm" :href="route('networks.connect', $key)" icon="key">Update keys</x-ui.button>
                            @endif
                            <form method="POST" action="{{ route('networks.destroy', $connection) }}" onsubmit="return confirm('Disconnect {{ e($network['label']) }}? Its keys are deleted. Imported earnings stay, and products go back to your own affiliate links.')">
                                @csrf
                                @method('DELETE')
                                <x-ui.button size="sm" variant="danger" icon="x-mark">Disconnect</x-ui.button>
                            </form>
                        </div>
                    @endif
                @else
                    <div class="mt-auto pt-4">
                        @if (! $isOwner)
                            <p class="text-xs text-ink-400">The account owner connects networks.</p>
                        @elseif (! $included)
                            <x-ui.button size="sm" variant="secondary" :href="route('billing.index')" icon="sparkles">Upgrade to connect</x-ui.button>
                        @elseif ($atLimit)
                            <p class="text-xs text-ink-600">You've used your plan's {{ NetworkConnectionService::limitLabel($limit) }}. Disconnect one or <a href="{{ route('billing.index') }}" class="font-medium text-brand-600 hover:text-brand-700">upgrade</a> to add {{ $network['label'] }}.</p>
                        @else
                            <x-ui.button size="sm" :href="route('networks.connect', $key)" icon="plus">Connect {{ $network['label'] }}</x-ui.button>
                        @endif
                    </div>
                @endif
            </x-ui.card>
        @endforeach
    </div>

    <div class="mt-8 grid gap-4 md:grid-cols-3">
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-heroicon-o-banknotes class="h-4 w-4 text-brand-600" /> Earnings, every day</p>
            <p class="mt-1.5 text-xs leading-relaxed text-ink-600">Commissions and their status (pending, approved, paid, reversed) sync daily into <a href="{{ route('earnings.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Earnings</a>.{{ $earningsCount ? ' '.number_format($earningsCount).' imported so far.' : '' }}</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-heroicon-o-link class="h-4 w-4 text-brand-600" /> Network tracked links</p>
            <p class="mt-1.5 text-xs leading-relaxed text-ink-600">Products added from a network use its tracked link, deep-linked to the product page where allowed. Your own link stays as a backup.</p>
        </div>
        <div class="rounded-2xl border border-line bg-surface p-5">
            <p class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-heroicon-o-document-magnifying-glass class="h-4 w-4 text-brand-600" /> Sales traced to content</p>
            <p class="mt-1.5 text-xs leading-relaxed text-ink-600">Every article, video and post gets its own link code, passed to the network as a sub-ID, so you see which piece of content earned each sale.</p>
        </div>
    </div>
@endsection
