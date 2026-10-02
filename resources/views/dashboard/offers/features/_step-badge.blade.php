@if ($done)
    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white shadow-sm" aria-label="Step {{ $number }}, done"><x-heroicon-m-check class="h-5 w-5" /></span>
@else
    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-rose-500 to-pink-600 font-display text-sm font-semibold text-white shadow-sm" aria-label="Step {{ $number }}">{{ $number }}</span>
@endif
