@props(['text', 'label' => 'Copy', 'size' => 'sm', 'tone' => 'default'])

{{-- One-click copy for any generated text. The text travels in a data
     attribute (escaped by Blade), never inline in a script. --}}
@php
    $tones = [
        'default' => 'bg-white text-navy-900 ring-1 ring-line hover:bg-surface-muted',
        'light' => 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25',
        'ghost' => 'text-brand-600 hover:bg-brand-50',
    ];
    $sizes = ['xs' => 'px-2 py-1 text-[11px]', 'sm' => 'px-2.5 py-1.5 text-xs', 'md' => 'px-3.5 py-2 text-sm'];
@endphp
<button type="button" data-copy="{{ $text }}" data-copy-label="{{ $label }}"
        {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 '.($tones[$tone] ?? $tones['default']).' '.($sizes[$size] ?? $sizes['sm'])]) }}>
    <x-heroicon-m-clipboard-document class="h-3.5 w-3.5 shrink-0" aria-hidden="true" />
    <span data-copy-text>{{ $label }}</span>
</button>

@once
    @push('scripts')
        <x-ui.copy-script />
    @endpush
@endonce
