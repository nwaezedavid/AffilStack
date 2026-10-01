@props(['title', 'subtitle' => null, 'icon' => null, 'eyebrow' => null, 'tone' => 'navy'])

{{-- The hero band at the top of a dashboard page: what this page is for, in
     one line, plus its primary actions on the right. --}}
@php
    $tones = [
        'navy' => 'from-navy-900 via-navy-800 to-brand-600',
        'gold' => 'from-navy-900 via-navy-800 to-gold-600',
        'emerald' => 'from-emerald-900 via-emerald-800 to-teal-600',
        'violet' => 'from-navy-900 via-violet-900 to-violet-600',
        'rose' => 'from-navy-900 via-rose-900 to-rose-600',
    ];
@endphp
<section {{ $attributes->merge(['class' => 'relative overflow-hidden rounded-2xl bg-gradient-to-br '.($tones[$tone] ?? $tones['navy']).' text-white px-6 py-6 sm:px-8 sm:py-7 mb-6 shadow-sm']) }}>
    <div class="pointer-events-none absolute -right-16 -top-16 h-56 w-56 rounded-full bg-white/10 blur-2xl" aria-hidden="true"></div>
    <div class="pointer-events-none absolute right-24 -bottom-20 h-40 w-40 rounded-full bg-gold-400/20 blur-2xl" aria-hidden="true"></div>
    <div class="relative flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
        <div class="flex items-start gap-4 min-w-0">
            @if ($icon)
                <span class="hidden sm:inline-flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-white/15 ring-1 ring-white/20">
                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6 text-gold-300" />
                </span>
            @endif
            <div class="min-w-0">
                @if ($eyebrow)
                    <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-300 mb-1">{{ $eyebrow }}</p>
                @endif
                <h2 class="font-display font-semibold text-2xl leading-tight text-balance">{{ $title }}</h2>
                @if ($subtitle)
                    <p class="mt-1.5 text-sm text-white/80 max-w-2xl">{{ $subtitle }}</p>
                @endif
            </div>
        </div>
        @isset($actions)
            <div class="flex flex-wrap items-center gap-2 shrink-0">{{ $actions }}</div>
        @endisset
    </div>
</section>
