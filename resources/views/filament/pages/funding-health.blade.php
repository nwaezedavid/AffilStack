<x-filament-panels::page>
    <p class="text-sm text-gray-500 dark:text-gray-400 mb-6">
        Vault watches every account that needs to stay funded so a user's action never fails mid-flight. "Run checks
        now" re-checks HeyGen and the payout wallet live, and re-evaluates whether either manual reminder has fallen
        due.
    </p>

    <div class="grid gap-3 mb-8">
        @foreach ($items as $item)
            <div class="rounded-xl border border-gray-200 dark:border-white/10 p-4">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $item['label'] }}</span>

                            @if ($item['kind'] === 'live')
                                @if (! ($item['is_configured'] ?? true))
                                    <x-filament::badge color="gray">Not configured</x-filament::badge>
                                @elseif ($item['is_low'])
                                    <x-filament::badge color="danger">Low — top up now</x-filament::badge>
                                @else
                                    <x-filament::badge color="success">Funded</x-filament::badge>
                                @endif
                            @else
                                @if ($item['is_due'])
                                    <x-filament::badge color="warning">Reminder due</x-filament::badge>
                                @else
                                    <x-filament::badge color="success">Up to date</x-filament::badge>
                                @endif
                            @endif
                        </div>

                        @if ($item['kind'] === 'live')
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">
                                @if ($item['balance'] !== null)
                                    Balance: {{ is_int($item['balance']) && ($item['currency'] ?? null) ? number_format($item['balance'] / 100, 2).' '.$item['currency'] : number_format($item['balance']) }}
                                    &mdash; threshold: {{ ($item['currency'] ?? null) ? number_format($item['threshold'] / 100, 2).' '.$item['currency'] : number_format($item['threshold']) }}
                                @else
                                    Not checked yet.
                                @endif
                            </p>
                            @if ($item['checked_at'])
                                <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">Last checked {{ \Illuminate\Support\Carbon::parse($item['checked_at'])->diffForHumans() }}</p>
                            @endif
                        @else
                            <p class="text-xs text-gray-600 dark:text-gray-300 mt-1">{{ $item['why_manual'] }}</p>
                            <p class="text-xs text-gray-400 dark:text-gray-500 mt-1">
                                Reminder every {{ $item['reminder_days'] }} days
                                @if ($item['last_acknowledged_at'])
                                    &mdash; last confirmed {{ \Illuminate\Support\Carbon::parse($item['last_acknowledged_at'])->diffForHumans() }}
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="flex flex-col items-end gap-2 shrink-0">
                        <a href="{{ $item['settings_url'] }}" class="text-xs text-primary-600 dark:text-primary-400 hover:underline">Open settings</a>
                        @if ($item['kind'] === 'manual')
                            <button wire:click="acknowledgeReminder('{{ $item['key'] }}')" type="button" class="text-xs text-gray-500 dark:text-gray-400 hover:underline">
                                I topped up — reset reminder
                            </button>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
