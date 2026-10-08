@props(['value', 'label' => null])
@if ($label)
    <span class="afs-g-copy-label">{{ $label }}</span>
@endif
<div class="afs-g-copy" x-data="{ copied: false, text: @js((string) $value) }">
    <code>{{ $value }}</code>
    <button type="button" aria-label="Copy {{ $label ?? 'value' }}"
        x-on:click="(async () => { try { await navigator.clipboard.writeText(text) } catch (e) { const t = document.createElement('textarea'); t.value = text; document.body.appendChild(t); t.select(); document.execCommand('copy'); t.remove() } copied = true; setTimeout(() => copied = false, 1600) })()">
        <x-filament::icon icon="heroicon-o-clipboard-document" />
        <span x-text="copied ? 'Copied' : 'Copy'">Copy</span>
    </button>
</div>
