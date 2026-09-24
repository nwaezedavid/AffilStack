<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    <p class="afs-panel-lead">
        Every external integration configured elsewhere in this dashboard, in one place. "Run all live checks" re-pings
        everything that can be verified with a stored key alone — a few (LinkedIn, TikTok, Instagram) can only ever be
        confirmed by clicking through the real frontend flow, noted below each one.
    </p>

    @foreach (collect($items)->groupBy('group') as $group => $groupItems)
        <h2 class="afs-panel-section-title">{{ $group }}</h2>

        <div class="afs-panel-check-list">
            @foreach ($groupItems as $item)
                <div class="afs-panel-card">
                    <div class="afs-panel-check-head">
                        <div>
                            <div class="afs-panel-inline-badges">
                                <span class="afs-panel-check-name">{{ $item['label'] }}</span>

                                @if ($item['success'] === true)
                                    <x-filament::badge color="success">Verified</x-filament::badge>
                                @elseif ($item['success'] === false)
                                    <x-filament::badge color="danger">Failed</x-filament::badge>
                                @elseif ($item['is_configured'])
                                    <x-filament::badge color="warning">Configured — not verified</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray">Not configured</x-filament::badge>
                                @endif

                                @if ($item['is_enabled'])
                                    <x-filament::badge color="success" icon="heroicon-o-eye">Live for users</x-filament::badge>
                                @else
                                    <x-filament::badge color="gray" icon="heroicon-o-eye-slash">Hidden from users</x-filament::badge>
                                @endif
                            </div>
                            <p class="afs-panel-check-meta">{{ $item['what_it_does'] }}</p>
                            @if ($item['message'])
                                <p class="afs-panel-check-meta">{{ $item['message'] }}</p>
                            @endif
                            <p class="afs-panel-check-hint">Frontend check: {{ $item['frontend_hint'] }}</p>

                            @if (! empty($aiExplanations[$item['key']]))
                                <p class="afs-panel-ai-note">
                                    AI assistant: {{ $aiExplanations[$item['key']] }}
                                </p>
                            @endif
                        </div>

                        <div class="afs-panel-check-actions">
                            <a href="{{ $item['settings_url'] }}" class="afs-panel-link">Open settings</a>
                            @if ($item['success'] !== true)
                                <button wire:click="explain('{{ $item['key'] }}')" type="button" class="afs-panel-btn-plain">
                                    Explain with AI
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endforeach
</x-filament-panels::page>
