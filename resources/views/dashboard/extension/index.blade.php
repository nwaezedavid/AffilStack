@extends('layouts.app')

@section('title', 'Browser Extension')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Capture a product page, competitor ad, or LinkedIn post while you're browsing — the extension sends it
        straight to AffilStack, where you can attach it to an offer or spin up a new one from it.
    </p>

    {{-- Install --}}
    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">1. Install the extension</h3>
        <ol class="text-sm text-ink-600 space-y-1.5 list-decimal list-inside mb-4">
            <li>Download the extension below and unzip it.</li>
            <li>In Chrome, go to <span class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">chrome://extensions</span> and turn on Developer mode (top right).</li>
            <li>Click "Load unpacked" and select the unzipped <span class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">affilstack-extension</span> folder.</li>
            <li>Click the extension icon, open its options, and paste in a token from the box below.</li>
        </ol>
        <a href="{{ route('extension.download') }}" class="inline-flex items-center rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2 hover:bg-navy-800 transition">
            Download extension (.zip)
        </a>
    </div>

    {{-- Tokens --}}
    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">2. Connect it to your account</h3>
        <p class="text-sm text-ink-600 mb-4">The extension authenticates with a token, not your password. Give each device its own token so you can revoke one without affecting the others.</p>

        <form method="POST" action="{{ route('extension.tokens.store') }}" class="flex items-center gap-2 mb-5 flex-wrap">
            @csrf
            <input name="name" required placeholder="e.g. Work laptop" class="rounded-md border border-line px-3 py-2 text-sm">
            <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Generate token</button>
        </form>

        @if ($tokens->isEmpty())
            <p class="text-sm text-ink-500">No tokens yet — generate one above and paste it into the extension's options.</p>
        @else
            <div class="border border-line rounded-md overflow-hidden">
                <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                        <tr>
                            <th class="text-left px-4 py-2">Name</th>
                            <th class="text-left px-4 py-2">Last used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-ink-900">{{ $token->name }}</td>
                                <td class="px-4 py-2.5 text-ink-600">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('extension.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Any extension using it will stop working.')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                </div>
            </div>
        @endif
    </div>

    {{-- Captured clips --}}
    <div class="bg-surface border border-line rounded-lg p-5">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Captured pages</h3>
        @if ($clips->isEmpty())
            <p class="text-sm text-ink-500">Nothing captured yet — once the extension is connected, use it on any page and it'll show up here.</p>
        @else
            <div class="divide-y divide-line-soft">
                @foreach ($clips as $clip)
                    <div class="py-3">
                        <div class="flex items-center gap-2 flex-wrap text-xs text-ink-400 font-mono uppercase tracking-wide">
                            <span>{{ config('extension.page_types')[$clip->page_type] ?? $clip->page_type }}</span>
                            <span>&middot;</span>
                            <span>{{ $clip->created_at->format('M j, Y') }}</span>
                            @if ($clip->offer)
                                <span>&middot;</span>
                                <span class="normal-case">attached to {{ $clip->offer->product_name }}</span>
                            @endif
                        </div>
                        <div class="text-sm font-medium text-ink-900 mt-1">{{ $clip->title ?: $clip->source_url }}</div>
                        <a href="{{ $clip->source_url }}" target="_blank" rel="noopener" class="text-xs text-brand-600 hover:text-brand-700 break-all">{{ $clip->source_url }}</a>
                        @if ($clip->selected_text)
                            <p class="text-sm text-ink-600 mt-2 line-clamp-2">{{ $clip->selected_text }}</p>
                        @endif

                        <div class="flex items-center gap-3 flex-wrap mt-2.5">
                            <form method="POST" action="{{ route('extension.clips.attach', $clip) }}" class="flex items-center gap-2">
                                @csrf @method('PATCH')
                                <select name="offer_id" onchange="this.form.submit()" class="rounded-md border border-line px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    <option value="">Not attached</option>
                                    @foreach ($offers as $offer)
                                        <option value="{{ $offer->id }}" @selected($clip->offer_id === $offer->id)>{{ $offer->product_name }}</option>
                                    @endforeach
                                </select>
                            </form>
                            @unless ($clip->offer_id)
                                <a href="{{ route('offers.create', ['product_name' => $clip->title, 'product_url' => $clip->source_url, 'clip_id' => $clip->id]) }}" class="text-xs text-brand-600 hover:text-brand-700">
                                    Create offer from this &rarr;
                                </a>
                            @endunless
                            <form method="POST" action="{{ route('extension.clips.destroy', $clip) }}" onsubmit="return confirm('Remove this captured page?')" class="ml-auto">
                                @csrf @method('DELETE')
                                <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endsection
