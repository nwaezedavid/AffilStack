<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Every external integration configured elsewhere in this dashboard, in one place. "Run all live checks" re-pings
        everything that can be verified with a stored key alone — a few (LinkedIn, TikTok, Instagram) can only ever be
        confirmed by clicking through the real frontend flow, noted below each one.
    </p>

    @foreach (collect($items)->groupBy('group') as $group => $groupItems)
        <h2 class="text-sm font-semibold text-gray-700 dark:text-gray-200 mt-8 mb-3">{{ $group }}</h2>

        <div class="grid gap-3">
            @foreach ($groupItems as $item)
                <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>

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
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ $item['what_it_does'] }}</p>
                            @if ($item['message'])
                                <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $item['message'] }}</p>
                            @endif
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Frontend check: {{ $item['frontend_hint'] }}</p>

                            @if (! empty($aiExplanations[$item['key']]))
                                <p class="text-xs text-primary-600 dark:text-primary-400 mt-2 border-l-2 border-primary-400 pl-2">
                                    AI assistant: {{ $aiExplanations[$item['key']] }}
                                </p>
                            @endif
                        </div>

                        <div class="flex flex-col items-end gap-2 shrink-0">
                            <a href="{{ $item['settings_url'] }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Open settings</a>
                            @if ($item['success'] !== true)
                                <button wire:click="explain('{{ $item['key'] }}')" type="button" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">
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
