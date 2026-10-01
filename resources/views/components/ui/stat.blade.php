@props(['label', 'value', 'icon' => null, 'hint' => null, 'tone' => 'brand'])

@php
    $tones = [
        'brand' => 'bg-brand-50 text-brand-600',
        'gold' => 'bg-gold-300/25 text-gold-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'violet' => 'bg-violet-50 text-violet-600',
        'rose' => 'bg-rose-50 text-rose-600',
    ];
@endphp
<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    <div class="flex items-center justify-between gap-2">
        <p class="text-xs font-medium text-ink-600">{{ $label }}</p>
        @if ($icon)
            <span class="inline-flex h-8 w-8 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['brand'] }}">
                <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-4 w-4" />
            </span>
        @endif
    </div>
    <p class="mt-2 font-display text-2xl font-semibold text-navy-900 tabular-nums">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 text-xs text-ink-400">{{ $hint }}</p>
    @endif
</div>
