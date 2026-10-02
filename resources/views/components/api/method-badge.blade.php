@props(['method'])

@php
    $tones = [
        'GET' => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'POST' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'PATCH' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'PUT' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'DELETE' => 'bg-rose-50 text-rose-700 ring-rose-600/20',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex w-16 shrink-0 justify-center rounded-md px-2 py-0.5 font-mono text-[11px] font-semibold ring-1 ring-inset '.($tones[$method] ?? $tones['GET'])]) }}>{{ $method }}</span>
