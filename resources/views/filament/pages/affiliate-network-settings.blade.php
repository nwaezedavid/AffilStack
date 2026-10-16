<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem; display: flex; gap: 0.75rem; flex-wrap: wrap;">
            <x-filament::button type="submit">
                Save changes
            </x-filament::button>
            @if (\App\Services\Networks\Adapters\Digistore24Adapter::canApproveInBrowser())
                <x-filament::button color="danger" outlined wire:click="removeDigistoreKey" wire:confirm="Remove the Digistore24 developer key? Customers will paste API keys instead.">
                    Remove Digistore24 key
                </x-filament::button>
            @endif
        </div>
    </form>
</x-filament-panels::page>
