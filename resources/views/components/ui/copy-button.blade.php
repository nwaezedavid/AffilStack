@props([
    'text' => null,
    'target' => null,
    'label' => 'Copy',
    'copied' => 'Copied!',
    'size' => 'sm',
    'tone' => null,
    'variant' => null,
])

{{-- One-click copy. Copies either a literal string ("text", carried in an
     escaped data attribute — never inline in a script) or the value/text of
     the element with id="target", then shows "Copied!" for two seconds.
     "tone" (default|light|ghost) and "variant" (secondary|primary|gold|light)
     are interchangeable styling names. --}}
@php
    $styles = [
        'default' => 'bg-white text-navy-900 ring-1 ring-line hover:bg-surface-muted',
        'secondary' => 'bg-white text-navy-900 ring-1 ring-line hover:bg-surface-muted',
        'primary' => 'bg-navy-900 text-white hover:bg-navy-800 shadow-sm',
        'gold' => 'bg-gold-400 text-navy-950 hover:bg-gold-300 shadow-sm',
        'light' => 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25',
        'ghost' => 'text-brand-600 hover:bg-brand-50',
    ];
    $style = $styles[$variant ?? $tone ?? 'default'] ?? $styles['default'];
    $sizes = ['xs' => 'px-2 py-1 text-[11px]', 'sm' => 'px-2.5 py-1.5 text-xs', 'md' => 'px-3.5 py-2 text-sm'];
@endphp
<button type="button"
        @if ($text !== null) data-copy="{{ $text }}" @endif
        @if ($target) data-copy-target="{{ $target }}" @endif
        data-copy-label="{{ $label }}"
        data-copied-label="{{ $copied }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center justify-center gap-1 rounded-full font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 '.$style.' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    <x-heroicon-m-clipboard-document class="h-3.5 w-3.5 shrink-0" data-copy-icon aria-hidden="true" />
    <x-heroicon-m-check class="h-3.5 w-3.5 shrink-0 hidden text-emerald-500" data-copied-icon aria-hidden="true" />
    <span data-copy-text aria-live="polite">{{ $label }}</span>
</button>

@once
    @push('scripts')
        <x-ui.copy-script />
    @endpush
@endonce
