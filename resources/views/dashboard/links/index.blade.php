@extends('layouts.app')

@section('title', 'Links & Clicks')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-5">Every affiliate-link placeholder in your generated content becomes one of these cloaked links automatically — one per offer per channel, so you can see which channel is actually driving clicks.</p>

    @if ($links->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            No cloaked links yet — they're created the first time you view generated content that includes a link.
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Offer</th>
                        <th class="text-left px-4 py-2.5">Channel</th>
                        <th class="text-left px-4 py-2.5">Link</th>
                        <th class="text-right px-4 py-2.5">Clicks</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($links as $link)
                        <tr>
                            <td class="px-4 py-3 text-ink-900">{{ $link->offer->product_name }}</td>
                            <td class="px-4 py-3 text-ink-600 capitalize">{{ str_replace('_', ' ', $link->module) }}</td>
                            <td class="px-4 py-3">
                                <code class="text-xs bg-surface-muted rounded px-2 py-1">{{ $link->short_url }}</code>
                            </td>
                            <td class="px-4 py-3 text-right font-mono text-ink-900">{{ number_format($link->clicks_count) }}</td>
                            <td class="px-4 py-3 text-right">
                                <a href="{{ route('links.show', $link) }}" class="text-xs text-brand-600 hover:text-brand-700">View analytics &rarr;</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        </div>
    @endif
@endsection
