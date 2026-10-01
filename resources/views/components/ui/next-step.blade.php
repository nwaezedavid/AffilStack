@props(['title' => 'What to do next', 'href' => null, 'action' => null])

{{-- The "what to do next" nudge shown after every step of a feature, so a
     user is never left on a page wondering where to go. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-3 rounded-2xl border border-gold-300/60 bg-gradient-to-r from-gold-300/15 via-white to-brand-50 p-4 sm:flex-row sm:items-center sm:justify-between']) }}>
    <div class="flex items-start gap-3">
        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gold-400 text-navy-950">
            <x-heroicon-s-arrow-right class="h-4 w-4" />
        </span>
        <div>
            <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-gold-600">{{ $title }}</p>
            <div class="text-sm text-ink-900 mt-0.5">{{ $slot }}</div>
        </div>
    </div>
    @if ($href && $action)
        <a href="{{ $href }}" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-medium text-white hover:bg-navy-800 transition">
            {{ $action }} <x-heroicon-m-arrow-right class="h-4 w-4" />
        </a>
    @endif
</div>
