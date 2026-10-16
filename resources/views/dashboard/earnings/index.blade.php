@extends('layouts.app')

@section('title', 'Earnings')

@section('content')
    @php
        use App\Support\ReferralProgram;
        use App\Support\ExchangeRates;
        $fmt = fn (int $cents) => ReferralProgram::money($cents, $currency);
    @endphp

    <div class="mb-5 flex flex-wrap items-start justify-between gap-4">
        <p class="max-w-2xl text-sm text-ink-600">Commissions from your connected affiliate networks arrive here automatically every day. Each sale is traced back to the product, channel and piece of content that made it, through the code every <span class="font-mono">/go/</span> link passes to the network. You can still add entries by hand or import a CSV.</p>
        <form method="POST" action="{{ route('earnings.currency') }}" class="flex items-center gap-2 text-xs">
            @csrf
            @method('PATCH')
            <span class="text-ink-400">Show totals in</span>
            <div class="inline-flex rounded-full border border-line bg-surface p-0.5" role="group" aria-label="Currency for totals">
                @foreach (['USD' => 'US$', 'NGN' => '₦ Naira'] as $code => $label)
                    <button type="submit" name="currency" value="{{ $code }}" aria-pressed="{{ $currency === $code ? 'true' : 'false' }}" class="rounded-full px-3 py-1 font-medium transition {{ $currency === $code ? 'bg-navy-900 text-white' : 'text-ink-600 hover:text-navy-900' }}">{{ $label }}</button>
                @endforeach
            </div>
        </form>
    </div>

    @if ($connections->isNotEmpty())
        <div class="mb-6 flex flex-wrap items-center gap-2 rounded-lg border border-line bg-surface px-4 py-3 text-xs">
            <span class="font-medium text-navy-900">Synced from</span>
            @foreach ($connections as $connection)
                <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 {{ $connection->status === 'error' ? 'bg-rose-50 text-rose-700' : 'bg-surface-muted text-ink-600' }}">
                    <span class="h-1.5 w-1.5 rounded-full {{ $connection->status === 'error' ? 'bg-rose-500' : ($connection->last_synced_at ? 'bg-emerald-500' : 'bg-amber-400') }}"></span>
                    {{ $connection->label() }} · {{ $connection->status === 'error' ? 'needs attention' : ($connection->last_synced_at ? $connection->last_synced_at->diffForHumans() : 'first sync running') }}
                </span>
            @endforeach
            <a href="{{ route('networks.index') }}" class="ml-auto font-medium text-brand-600 hover:text-brand-700">Manage networks →</a>
        </div>
    @else
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3 rounded-lg border border-dashed border-line bg-surface px-4 py-3 text-sm">
            <span class="text-ink-600">Connect Impact, PartnerStack, Awin, CJ, ClickBank or Digistore24 and your commissions arrive here on their own.</span>
            <a href="{{ route('networks.index') }}" class="font-medium text-brand-600 hover:text-brand-700">Connect a network →</a>
        </div>
    @endif

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-2">
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Total earnings</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $fmt($totals['total_cents']) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Pending</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $fmt($totals['pending_cents']) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Approved</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $fmt($totals['approved_cents']) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Paid</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $fmt($totals['paid_cents']) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Earnings per click</div>
            <div class="text-2xl font-display font-semibold text-navy-900">{{ $fmt($epcCents) }}</div>
            <div class="text-xs text-ink-400 mt-1">across {{ number_format($totalClicks) }} clicks</div>
        </div>
    </div>
    @if ($currencies->reject(fn ($code) => $code === $currency)->isNotEmpty())
        <p class="mb-8 text-xs text-ink-400">Sales paid in {{ $currencies->implode(', ') }} are converted at daily exchange rates{{ ExchangeRates::updatedOn() ? ' (updated '.\Illuminate\Support\Carbon::parse(ExchangeRates::updatedOn())->format('M j').')' : '' }}. Each sale below shows its original amount.</p>
    @else
        <div class="mb-8"></div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-6">
            <h2 class="text-sm font-semibold text-navy-900 mb-3">Add an entry manually</h2>
            <form method="POST" action="{{ route('earnings.store') }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Network</label>
                        <select name="network" required class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                            @foreach ($networks as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Amount</label>
                        <div class="flex gap-1.5">
                            <input type="number" step="0.01" min="0.01" name="amount" required class="w-full min-w-0 rounded-md border border-line px-2.5 py-2 text-sm">
                            <select name="currency" aria-label="Currency" class="rounded-md border border-line px-1.5 py-2 text-sm">
                                @foreach (['USD', 'EUR', 'GBP', 'NGN', 'CAD', 'AUD'] as $code)
                                    <option value="{{ $code }}">{{ $code }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Status</label>
                        <select name="status" required class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="paid">Paid</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-ink-900 mb-1">Date</label>
                        <input type="date" name="converted_at" required max="{{ now()->toDateString() }}" value="{{ now()->toDateString() }}" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-900 mb-1">Link (optional — attributes it to an offer &amp; channel)</label>
                    <select name="tracked_link_id" class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                        <option value="">— Not tied to a specific link —</option>
                        @foreach ($trackedLinks as $link)
                            <option value="{{ $link->id }}">{{ $link->offer->product_name }} — {{ str_replace('_', ' ', $link->module) }} ({{ $link->code }})</option>
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2 hover:bg-navy-800 transition">Add entry</button>
            </form>
        </div>

        <div class="bg-surface border border-line rounded-lg p-6">
            <h2 class="text-sm font-semibold text-navy-900 mb-3">Import a CSV</h2>
            <p class="text-xs text-ink-400 mb-3">Export your conversions/payouts from your network and upload the file — column headers like "Date", "Amount", "Status", and "Sub ID" are detected automatically.</p>
            <form method="POST" action="{{ route('earnings.import') }}" enctype="multipart/form-data" class="space-y-3">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-ink-900 mb-1">Network</label>
                    <select name="network" required class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
                        @foreach ($networks as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-medium text-ink-900 mb-1">CSV file</label>
                    <input type="file" name="file" accept=".csv,text/csv" required class="w-full text-sm">
                </div>
                <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2 hover:bg-navy-800 transition">Import</button>
            </form>
        </div>
    </div>

    @if ($byOffer->isNotEmpty())
        <h2 class="text-sm font-semibold text-navy-900 mb-3">By offer</h2>
        <div class="bg-surface border border-line rounded-lg overflow-hidden mb-8">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Offer</th>
                        <th class="text-right px-4 py-2.5">Clicks</th>
                        <th class="text-right px-4 py-2.5">Earnings</th>
                        <th class="text-right px-4 py-2.5">EPC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($byOffer as $offer)
                        <tr>
                            <td class="px-4 py-3 text-ink-900">{{ $offer->product_name }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ number_format($offer->clicks) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $fmt($offer->earnings_cents) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $offer->clicks > 0 ? $fmt((int) round($offer->earnings_cents / $offer->clicks)) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    @if ($byChannel->isNotEmpty())
        <h2 class="text-sm font-semibold text-navy-900 mb-3">By channel</h2>
        <div class="bg-surface border border-line rounded-lg overflow-hidden mb-8">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Channel</th>
                        <th class="text-right px-4 py-2.5">Clicks</th>
                        <th class="text-right px-4 py-2.5">Earnings</th>
                        <th class="text-right px-4 py-2.5">EPC</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($byChannel as $channel)
                        <tr>
                            <td class="px-4 py-3 text-ink-900 capitalize">{{ str_replace('_', ' ', $channel['module']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ number_format($channel['clicks']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $fmt($channel['earnings_cents']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $channel['clicks'] > 0 ? $fmt((int) round($channel['earnings_cents'] / $channel['clicks'])) : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    @if ($byContent->isNotEmpty())
        <h2 class="text-sm font-semibold text-navy-900 mb-3">By piece of content</h2>
        <div class="bg-surface border border-line rounded-lg overflow-hidden mb-8">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Content</th>
                        <th class="text-right px-4 py-2.5">Clicks</th>
                        <th class="text-right px-4 py-2.5">Sales</th>
                        <th class="text-right px-4 py-2.5">Earnings</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($byContent as $row)
                        @php
                            $gen = $row['generation'];
                        @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <a href="{{ \App\Support\OfferFeatures::urlForGeneration($gen) }}" class="font-medium text-navy-900 hover:text-brand-600">{{ \Illuminate\Support\Str::limit((string) ($gen->output_meta['title'] ?? $gen->offer?->product_name ?? 'Content'), 70) }}</a>
                                <div class="text-xs text-ink-400 capitalize">{{ str_replace('_', ' ', $gen->module) }} · {{ $gen->offer?->product_name }} · {{ $gen->created_at->format('M j, Y') }}</div>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ number_format($row['clicks']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ number_format($row['sales']) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ $fmt($row['earnings_cents']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    @if ($unmatched->isNotEmpty())
        <h2 class="text-sm font-semibold text-navy-900 mb-3">Unmatched entries — assign an offer{{ $unmatchedCount > $unmatched->count() ? ' (showing '.$unmatched->count().' of '.$unmatchedCount.')' : '' }}</h2>
        <div class="bg-surface border border-line rounded-lg overflow-hidden mb-8">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Date</th>
                        <th class="text-left px-4 py-2.5">Network</th>
                        <th class="text-right px-4 py-2.5">Amount</th>
                        <th class="text-left px-4 py-2.5">Assign to</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($unmatched as $entry)
                        <tr>
                            <td class="px-4 py-3 text-ink-600">{{ $entry->converted_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-ink-600">{{ $networks[$entry->network] ?? $entry->network }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ ReferralProgram::money($entry->amount_cents, $entry->currency ?: 'USD') }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('earnings.assign-offer', $entry) }}" class="flex items-center gap-2">
                                    @csrf
                                    @method('PATCH')
                                    <select name="offer_id" required class="rounded-md border border-line px-2 py-1 text-xs">
                                        <option value="">Choose an offer…</option>
                                        @foreach ($offers as $offer)
                                            <option value="{{ $offer->id }}">{{ $offer->product_name }}</option>
                                        @endforeach
                                    </select>
                                    <button type="submit" class="text-xs text-brand-600 hover:text-brand-700 font-medium">Assign</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif

    <h2 class="text-sm font-semibold text-navy-900 mb-3">All entries</h2>
    @if ($entries->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No earnings yet. Connect an affiliate network to import commissions automatically, or add one by hand above.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Date</th>
                        <th class="text-left px-4 py-2.5">Offer</th>
                        <th class="text-left px-4 py-2.5">Network</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                        <th class="text-right px-4 py-2.5">Amount</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($entries as $entry)
                        <tr>
                            <td class="px-4 py-3 text-ink-600">{{ $entry->converted_at->format('M j, Y') }}</td>
                            <td class="px-4 py-3 text-ink-900">
                                {{ $entry->offer->product_name ?? '—' }}
                                @if ($entry->generation)
                                    <div class="text-xs text-ink-400 capitalize">from {{ str_replace('_', ' ', $entry->generation->module) }}</div>
                                @elseif ($entry->description && ! $entry->offer)
                                    <div class="text-xs text-ink-400">{{ $entry->description }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-ink-600">{{ $networks[$entry->network] ?? $entry->network }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-0.5 {{ match($entry->status) { 'paid' => 'bg-green-100 text-green-800', 'approved' => 'bg-blue-100 text-blue-800', 'declined' => 'bg-rose-100 text-rose-800', default => 'bg-surface-muted text-ink-600' } }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ ReferralProgram::money($entry->amount_cents, $entry->currency ?: 'USD') }}</td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('earnings.destroy', $entry) }}" onsubmit="return confirm('Remove this entry?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-ink-400 hover:text-red-600">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
        <div class="mt-4">{{ $entries->links() }}</div>
    @endif
@endsection
