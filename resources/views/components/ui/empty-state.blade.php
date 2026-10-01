@props(['icon' => 'sparkles', 'title', 'href' => null, 'action' => null])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center rounded-2xl border border-dashed border-line bg-surface px-6 py-12 text-center']) }}>
    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-gradient-to-br from-brand-50 to-gold-300/30 text-brand-600">
        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6" />
    </span>
    <h3 class="mt-4 font-display font-semibold text-base text-navy-900">{{ $title }}</h3>
    @if (trim($slot) !== '')
        <p class="mt-1 max-w-md text-sm text-ink-600">{{ $slot }}</p>
    @endif
    @if ($href && $action)
        <a href="{{ $href }}" class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-4 py-2 text-sm font-medium text-white hover:bg-navy-800 transition">{{ $action }}</a>
    @endif
</div>
