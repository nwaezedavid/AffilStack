<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}

        {{-- Inline styles: the admin panel uses Filament's precompiled CSS, not the site's Tailwind build. --}}
        <div style="position: sticky; bottom: 0; z-index: 10; margin-top: 1.5rem; display: flex; flex-wrap: wrap; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; border-radius: 0.75rem; background: rgba(255,255,255,0.92); box-shadow: 0 -1px 0 rgba(0,0,0,0.06), 0 8px 24px -12px rgba(11,30,61,0.25); backdrop-filter: blur(6px);">
            <x-filament::button type="submit" icon="heroicon-m-check">Save changes</x-filament::button>
            <x-filament::link :href="$this->publicUrl()" target="_blank" icon="heroicon-m-arrow-top-right-on-square">View the page</x-filament::link>
            <span style="font-size: 0.75rem; color: #6b7280;">Saving updates the live site immediately.</span>
        </div>
    </form>
</x-filament-panels::page>
