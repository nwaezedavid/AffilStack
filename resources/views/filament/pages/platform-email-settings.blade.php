<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        <x-filament::button type="submit" style="margin-top: 1.5rem;">
            Save changes
        </x-filament::button>
    </form>
</x-filament-panels::page>
