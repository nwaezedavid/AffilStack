@extends('layouts.app')

@section('title', 'Connected Accounts')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Connect your own social accounts. LinkedIn is identity-only — AffilStack never posts or messages on your
        behalf there. YouTube, TikTok, and Instagram can be used to publish a rendered UGC video for you once the
        platform has approved AffilStack's app; until then, download the video and post it yourself.
    </p>

    <div class="grid gap-5 md:grid-cols-2">
        @foreach (['linkedin' => 'LinkedIn', 'youtube' => 'YouTube', 'tiktok' => 'TikTok', 'instagram' => 'Instagram'] as $key => $label)
            @php
                $connection = $connections->get($key);
                $provider = $providers[$key] ?? null;
            @endphp
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">{{ $label }}</h3>
                @if ($connection)
                    <p class="text-sm text-ink-600 mb-2">Connected as <span class="font-medium text-ink-900">{{ $connection->account_name }}</span></p>
                    @if (($provider['publishes'] ?? false))
                        @if ($provider['approved'] ?? false)
                            <p class="text-xs text-emerald-700 mb-3">✓ {{ $label }} has approved AffilStack for publishing.</p>
                        @else
                            <p class="text-xs text-ink-500 mb-3">⏳ Awaiting {{ $label }}'s approval — publishing will fall back to download-and-post-manually until then.</p>
                        @endif
                    @endif
                    <form method="POST" action="{{ route('social-connections.destroy', $key) }}" onsubmit="return confirm('Disconnect {{ $label }}?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:text-red-700">Disconnect</button>
                    </form>
                @elseif ($provider['available'] ?? false)
                    @if ($key === 'linkedin')
                        <p class="text-sm text-ink-600 mb-4">Content only — you export and post it yourself. AffilStack never publishes to LinkedIn for you.</p>
                    @else
                        <p class="text-sm text-ink-600 mb-4">Connect to let AffilStack publish a rendered UGC video for you, once {{ $label }} approves the app.</p>
                    @endif
                    <a href="{{ route('social-connections.redirect', $key) }}" class="inline-flex items-center gap-2 rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">
                        Connect {{ $label }}
                    </a>
                @else
                    <p class="text-sm text-ink-500">{{ $label }} connections aren't available yet — check back soon.</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection
