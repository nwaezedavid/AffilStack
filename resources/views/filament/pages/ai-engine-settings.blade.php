<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <div style="margin-top: 1.5rem; display: flex; gap: .75rem; flex-wrap: wrap;">
            <x-filament::button type="submit">Save changes</x-filament::button>
            <x-filament::button color="gray" wire:click="testAnthropic" type="button">Test Claude</x-filament::button>
            <x-filament::button color="gray" wire:click="testOpenAi" type="button">Test OpenAI</x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
