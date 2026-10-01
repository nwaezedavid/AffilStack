@props(['title' => null, 'description' => null, 'icon' => null, 'padding' => 'p-5 sm:p-6'])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]']) }}>
    @if ($title || isset($headerActions))
        <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4 sm:px-6">
            <div class="flex items-start gap-3 min-w-0">
                @if ($icon)
                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                    </span>
                @endif
                <div class="min-w-0">
                    @if ($title)
                        <h3 class="font-display font-semibold text-base text-navy-900">{{ $title }}</h3>
                    @endif
                    @if ($description)
                        <p class="text-sm text-ink-600 mt-0.5">{{ $description }}</p>
                    @endif
                </div>
            </div>
            @isset($headerActions)
                <div class="flex items-center gap-2 shrink-0">{{ $headerActions }}</div>
            @endisset
        </div>
    @endif
    <div class="{{ $padding }}">{{ $slot }}</div>
</div>
