<div class="rounded-2xl bg-surface-muted p-3 text-sm">
    <span class="font-semibold text-ink-900">Your current angle:</span> <span class="text-ink-600">{{ $offer->recommended_angle }}</span>
</div>
<div class="mt-3 grid gap-2.5 md:grid-cols-2">
    @foreach ($gen->output_meta['common_angles'] ?? [] as $angle)
        @php
            [$saturationTone, $saturationBar] = match ($angle['saturation'] ?? null) {
                'high' => ['bg-red-50 text-red-700 ring-red-200', 'w-full bg-red-500'],
                'medium' => ['bg-amber-50 text-amber-700 ring-amber-200', 'w-2/3 bg-amber-500'],
                default => ['bg-emerald-50 text-emerald-700 ring-emerald-200', 'w-1/3 bg-emerald-500'],
            };
        @endphp
        <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-semibold text-navy-900">{{ $angle['angle_name'] ?? '' }}</p>
                <span class="rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 {{ $saturationTone }}">{{ ucfirst($angle['saturation'] ?? 'unknown') }} saturation</span>
            </div>
            <div class="mt-2 h-1.5 w-full rounded-full bg-surface-muted"><div class="h-full rounded-full {{ $saturationBar }}"></div></div>
            <p class="mt-2 text-sm italic text-ink-900">&ldquo;{{ $angle['example_headline'] ?? '' }}&rdquo;</p>
            <p class="mt-1 text-xs text-ink-600">{{ $angle['why_it_works'] ?? '' }}</p>
            @if (! empty($angle['typical_channel']))
                <p class="mt-1 text-[11px] text-ink-400">Seen most on {{ $angle['typical_channel'] }}</p>
            @endif
        </div>
    @endforeach
</div>
<div class="mt-3 grid gap-3 md:grid-cols-2">
    <div class="rounded-2xl bg-gradient-to-br from-amber-50 to-white p-4 ring-1 ring-amber-200">
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Differentiation opportunity</p>
            @if (! empty($gen->output_meta['differentiation_opportunity']))
                <x-ui.copy-button :text="$gen->output_meta['differentiation_opportunity']" size="xs" />
            @endif
        </div>
        <p class="mt-1 text-sm text-ink-900">{{ $gen->output_meta['differentiation_opportunity'] ?? '' }}</p>
    </div>
    <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
        <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Suggested adjustment</p>
        <p class="mt-1 text-sm text-ink-900">{{ $gen->output_meta['recommended_adjustment'] ?? '' }}</p>
    </div>
</div>
