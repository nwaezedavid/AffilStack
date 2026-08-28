@extends('layouts.app')

@section('title', $trackedLink->offer->product_name.' — link analytics')

@section('content')
    <a href="{{ route('links.index') }}" class="text-xs text-ink-600 hover:text-ink-900">&larr; All links</a>

    <div class="bg-surface border border-line rounded-lg p-5 my-4">
        <h2 class="font-display font-semibold text-lg text-navy-900 mb-1">{{ $trackedLink->offer->product_name }}</h2>
        <p class="text-xs text-ink-600 mb-3 capitalize">{{ str_replace('_', ' ', $trackedLink->module) }} channel</p>
        <dl class="grid sm:grid-cols-3 gap-4 text-sm">
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Cloaked link</dt>
                <dd><code class="text-xs bg-surface-muted rounded px-2 py-1">{{ $trackedLink->short_url }}</code></dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Destination</dt>
                <dd class="text-ink-900 truncate">{{ $trackedLink->destination_url }}</dd>
            </div>
            <div>
                <dt class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Total clicks</dt>
                <dd class="font-mono text-lg text-navy-900">{{ number_format($trackedLink->clicks_count) }}</dd>
            </div>
        </dl>
    </div>

    <div class="grid sm:grid-cols-3 gap-4 mb-6">
        <div class="bg-surface border border-line rounded-lg p-5">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">By device</h3>
            @forelse ($byDevice as $device => $total)
                <div class="flex items-center justify-between text-sm py-1">
                    <span class="text-ink-600 capitalize">{{ $device }}</span>
                    <span class="font-mono text-ink-900">{{ number_format($total) }}</span>
                </div>
            @empty
                <p class="text-xs text-ink-400">No clicks yet.</p>
            @endforelse
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Top countries</h3>
            @forelse ($byCountry as $country => $total)
                <div class="flex items-center justify-between text-sm py-1">
                    <span class="text-ink-600">{{ $country }}</span>
                    <span class="font-mono text-ink-900">{{ number_format($total) }}</span>
                </div>
            @empty
                <p class="text-xs text-ink-400">No geolocated clicks yet.</p>
            @endforelse
        </div>
        <div class="bg-surface border border-line rounded-lg p-5">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Top referrers</h3>
            @forelse ($byReferrer as $referrer => $total)
                <div class="flex items-center justify-between text-sm py-1 gap-2">
                    <span class="text-ink-600 truncate" title="{{ $referrer }}">{{ $referrer }}</span>
                    <span class="font-mono text-ink-900 shrink-0">{{ number_format($total) }}</span>
                </div>
            @empty
                <p class="text-xs text-ink-400">No referrer data yet.</p>
            @endforelse
        </div>
    </div>

    <h3 class="font-display font-semibold text-sm text-navy-900 mb-3">Recent clicks</h3>
    @if ($clicks->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No clicks recorded yet.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">When</th>
                        <th class="text-left px-4 py-2.5">Device</th>
                        <th class="text-left px-4 py-2.5">Browser</th>
                        <th class="text-left px-4 py-2.5">Country</th>
                        <th class="text-left px-4 py-2.5">Referrer</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($clicks as $click)
                        <tr>
                            <td class="px-4 py-3 text-ink-600 text-xs">{{ $click->clicked_at->diffForHumans() }}</td>
                            <td class="px-4 py-3 text-ink-900 capitalize">{{ $click->device_type ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-900">{{ $click->browser ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-900">{{ $click->country ?? '—' }}</td>
                            <td class="px-4 py-3 text-ink-600 text-xs truncate max-w-xs" title="{{ $click->referrer }}">{{ $click->referrer ?? 'Direct' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $clicks->links() }}</div>
    @endif
@endsection
