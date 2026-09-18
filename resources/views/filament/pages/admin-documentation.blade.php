<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        A plain-language guide to every settings area in this dashboard — what it configures, why it exists, and how
        to confirm it's actually working on the frontend. For a live status check across every integration below,
        see Connections Health.
    </p>

    <div class="grid gap-4">
        @foreach ($this->sections() as $section)
            <div class="rounded-xl border border-gray-200 dark:border-white/10 p-5">
                <div class="flex items-center justify-between gap-4 mb-2">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $section['title'] }}</h2>

                    @if ($section['url'])
                        <a href="{{ $section['url'] }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline shrink-0">
                            Open settings
                        </a>
                    @endif
                </div>

                <div class="space-y-2">
                    @foreach ($section['body'] as $paragraph)
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $paragraph }}</p>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
</x-filament-panels::page>
