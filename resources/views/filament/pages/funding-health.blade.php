<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    <p class="afs-panel-lead">
        Vault watches every account that needs to stay funded so a user's action never fails mid-flight. "Run checks
        now" re-checks HeyGen and the payout wallet live, and re-evaluates whether either manual reminder has fallen
        due.
    </p>

    <div class="afs-panel-check-list">
        @foreach ($items as $item)
            <div class="afs-panel-card">
                <div class="afs-panel-check-head">
                    <div>
                        <div class="afs-panel-inline-badges">
                            <span class="afs-panel-check-name">{{ $item['label'] }}</span>

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
                            <p class="afs-panel-check-meta">
                                @if ($item['balance'] !== null)
                                    Balance: {{ is_int($item['balance']) && ($item['currency'] ?? null) ? number_format($item['balance'] / 100, 2).' '.$item['currency'] : number_format($item['balance']) }}
                                    &mdash; threshold: {{ ($item['currency'] ?? null) ? number_format($item['threshold'] / 100, 2).' '.$item['currency'] : number_format($item['threshold']) }}
                                @else
                                    Not checked yet.
                                @endif
                            </p>
                            @if ($item['checked_at'])
                                <p class="afs-panel-check-hint">Last checked {{ \Illuminate\Support\Carbon::parse($item['checked_at'])->diffForHumans() }}</p>
                            @endif
                        @else
                            <p class="afs-panel-check-meta">{{ $item['why_manual'] }}</p>
                            <p class="afs-panel-check-hint">
                                Reminder every {{ $item['reminder_days'] }} days
                                @if ($item['last_acknowledged_at'])
                                    &mdash; last confirmed {{ \Illuminate\Support\Carbon::parse($item['last_acknowledged_at'])->diffForHumans() }}
                                @endif
                            </p>
                        @endif
                    </div>

                    <div class="afs-panel-check-actions">
                        <a href="{{ $item['settings_url'] }}" class="afs-panel-link">Open settings</a>
                        @if ($item['kind'] === 'manual')
                            <button wire:click="acknowledgeReminder('{{ $item['key'] }}')" type="button" class="afs-panel-btn-plain">
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

        <div class="afs-panel-spacer-top">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
