<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    <div class="afs-panel-hero">
        <div class="afs-panel-hero-icon">
            <x-filament::icon icon="heroicon-o-credit-card" />
        </div>
        <div>
            <p>
                Only an enabled, verified gateway is ever offered to a customer at checkout — Flutterwave, Stripe,
                and PayPal to international customers, Paystack only to customers checking out from Nigeria. Expand
                a gateway below to enter its credentials, then use "Verify credentials" in that gateway's own header
                to confirm they actually work before relying on it for real payments.
            </p>
        </div>
    </div>

    <div class="afs-panel-grid" style="grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); margin-bottom: 1.5rem;">
        @foreach ($this->summaries() as $summary)
            <div class="afs-panel-card">
                <div class="afs-panel-card-head">
                    <div class="afs-panel-card-title">
                        <x-filament::icon :icon="$summary['icon']" />
                        {{ $summary['label'] }}
                    </div>
                    <span class="afs-panel-badge afs-panel-badge--{{ $summary['isEnabled'] ? 'success' : 'neutral' }}">
                        {{ $summary['isEnabled'] ? 'Enabled' : 'Disabled' }}
                    </span>
                </div>
                <p style="margin-bottom: 0.5rem;">{{ $summary['shownTo'] }}</p>
                <span class="afs-panel-badge afs-panel-badge--{{ $summary['status'] === 'verified' ? 'success' : ($summary['status'] === 'failed' ? 'danger' : 'neutral') }}">
                    {{ $summary['statusLabel'] }}
                </span>
            </div>
        @endforeach
    </div>

    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem;">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
