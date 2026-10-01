@props(['variant' => 'primary', 'href' => null, 'icon' => null, 'size' => 'md', 'type' => 'submit'])

@php
    $variants = [
        'primary' => 'bg-navy-900 text-white hover:bg-navy-800 shadow-sm',
        'brand' => 'bg-brand-600 text-white hover:bg-brand-700 shadow-sm',
        'gold' => 'bg-gold-400 text-navy-950 hover:bg-gold-300 shadow-sm',
        'secondary' => 'bg-white text-navy-900 ring-1 ring-line hover:bg-surface-muted',
        'ghost' => 'text-brand-600 hover:bg-brand-50',
        'danger' => 'bg-white text-red-600 ring-1 ring-red-200 hover:bg-red-50',
        'light' => 'bg-white/15 text-white ring-1 ring-white/25 hover:bg-white/25',
    ];
    $sizes = ['sm' => 'px-3 py-1.5 text-xs', 'md' => 'px-4 py-2 text-sm', 'lg' => 'px-5 py-2.5 text-sm'];
    $classes = 'inline-flex items-center justify-center gap-1.5 rounded-full font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 disabled:opacity-50 disabled:cursor-not-allowed '.($variants[$variant] ?? $variants['primary']).' '.($sizes[$size] ?? $sizes['md']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-m-'.$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon)<x-dynamic-component :component="'heroicon-m-'.$icon" class="h-4 w-4" />@endif
        {{ $slot }}
    </button>
@endif
