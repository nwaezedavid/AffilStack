@props(['title' => 'How this works', 'steps' => [], 'collapsible' => true, 'open' => false])

{{-- What a feature does and how to use it, as numbered steps. Collapsible
     so a returning user can tuck it away. --}}
@if ($collapsible)
    <details {{ $attributes->merge(['class' => 'group rounded-2xl border border-brand-100 bg-brand-50/60 p-4 sm:p-5']) }} @if ($open) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3">
            <span class="flex items-center gap-2 font-display font-semibold text-sm text-navy-900">
                <x-heroicon-o-light-bulb class="h-5 w-5 text-brand-600" /> {{ $title }}
            </span>
            <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-open:rotate-180" />
        </summary>
        <div class="mt-4">
            @if (trim($slot) !== '')
                <div class="text-sm text-ink-600 mb-4">{{ $slot }}</div>
            @endif
            <ol class="grid gap-3 sm:grid-cols-{{ min(max(count($steps), 1), 4) }}">
                @foreach ($steps as $i => $step)
                    <li class="flex gap-3 rounded-xl bg-white p-3 ring-1 ring-brand-100">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">{{ $i + 1 }}</span>
                        <span class="text-sm text-ink-900">{{ $step }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    </details>
@else
    <div {{ $attributes->merge(['class' => 'rounded-2xl border border-brand-100 bg-brand-50/60 p-4 sm:p-5']) }}>
        <p class="flex items-center gap-2 font-display font-semibold text-sm text-navy-900">
            <x-heroicon-o-light-bulb class="h-5 w-5 text-brand-600" /> {{ $title }}
        </p>
        @if (trim($slot) !== '')
            <div class="text-sm text-ink-600 mt-2">{{ $slot }}</div>
        @endif
        <ol class="mt-4 grid gap-3 sm:grid-cols-{{ min(max(count($steps), 1), 4) }}">
            @foreach ($steps as $i => $step)
                <li class="flex gap-3 rounded-xl bg-white p-3 ring-1 ring-brand-100">
                    <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-brand-600 text-[11px] font-semibold text-white">{{ $i + 1 }}</span>
                    <span class="text-sm text-ink-900">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </div>
@endif
