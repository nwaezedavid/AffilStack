@extends('layouts.app')

@section('title', 'Earnings')

@section('content')
    <p class="text-sm text-ink-600 max-w-2xl mb-5">Your affiliate networks track conversions on their own dashboards — this is where you bring that revenue in and map it back to the cloaked links above, so you can see which offer and which channel is actually making money, not just getting clicks. Every <span class="mono">/go/</span> link now passes its code through to the network as a <span class="font-mono text-xs bg-surface-muted rounded px-1">{{ config('earnings.tracking_param') }}</span> parameter — turn on "sub ID passthrough" (or equivalent) in your network's reporting export and a CSV import here will match rows to links automatically.</p>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Total earnings</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['total_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Pending</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['pending_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Approved</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['approved_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Paid</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($totals['paid_cents'] / 100, 2) }}</div>
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Earnings per click</div>
            <div class="text-2xl font-display font-semibold text-navy-900">${{ number_format($epcCents / 100, 3) }}</div>
            <div class="text-xs text-ink-500 mt-1">across {{ number_format($totalClicks) }} clicks</div>
        </div>
    </div>

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
                        <label class="block text-xs font-medium text-ink-900 mb-1">Amount ($)</label>
                        <input type="number" step="0.01" min="0.01" name="amount" required class="w-full rounded-md border border-line px-2.5 py-2 text-sm">
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
            <p class="text-xs text-ink-500 mb-3">Export your conversions/payouts from your network and upload the file — column headers like "Date", "Amount", "Status", and "Sub ID" are detected automatically.</p>
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
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($offer->earnings_cents / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ $offer->clicks > 0 ? number_format($offer->earnings_cents / $offer->clicks / 100, 3) : '—' }}</td>
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
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($channel['earnings_cents'] / 100, 2) }}</td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ $channel['clicks'] > 0 ? number_format($channel['earnings_cents'] / $channel['clicks'] / 100, 3) : '—' }}</td>
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
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($entry->amount_cents / 100, 2) }}</td>
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
            No earnings recorded yet — add one manually or import a CSV export from your affiliate network above.
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
                            <td class="px-4 py-3 text-ink-900">{{ $entry->offer->product_name ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-600">{{ $networks[$entry->network] ?? $entry->network }}</td>
                            <td class="px-4 py-3">
                                <span class="text-xs rounded-full px-2 py-0.5 {{ match($entry->status) { 'paid' => 'bg-green-100 text-green-800', 'approved' => 'bg-blue-100 text-blue-800', default => 'bg-surface-muted text-ink-600' } }}">
                                    {{ ucfirst($entry->status) }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">${{ number_format($entry->amount_cents / 100, 2) }}</td>
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
