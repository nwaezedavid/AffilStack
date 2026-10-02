@props(['label', 'value', 'mono' => true, 'copy' => true])

{{-- One "field → exact value" row for the no-code recipes, with a copy button. --}}
<div {{ $attributes->merge(['class' => 'flex flex-col gap-1 border-b border-line py-2 last:border-0 sm:flex-row sm:items-center sm:gap-4']) }}>
    <dt class="text-xs font-medium text-ink-600 sm:w-44 sm:shrink-0">{{ $label }}</dt>
    <dd class="flex min-w-0 flex-1 items-center justify-between gap-2">
        <span @class(['min-w-0 break-all text-navy-900', 'font-mono text-xs' => $mono, 'text-sm' => ! $mono])>{{ $value }}</span>
        @if ($copy)
            <button type="button" data-copy-text="{{ $value }}" class="inline-flex shrink-0 items-center gap-1 rounded-md px-1.5 py-0.5 text-[11px] font-medium text-brand-600 hover:bg-brand-50" aria-label="Copy {{ $label }}">
                <span data-copy-label>Copy</span>
            </button>
        @endif
    </dd>
</div>
