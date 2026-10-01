<div class="flex flex-wrap items-center gap-2 text-xs text-ink-600">
    @if (! empty($gen->output_meta['board_suggestion']))
        <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2.5 py-1 font-medium text-red-700"><x-heroicon-m-rectangle-stack class="h-3.5 w-3.5" /> Board: {{ $gen->output_meta['board_suggestion'] }}</span>
    @endif
</div>
<div class="mt-3 grid gap-3 md:grid-cols-3">
    @foreach ($gen->output_meta['pins'] ?? [] as $i => $pin)
        @php
            $pinText = $offer->cloak($pin['description'] ?? '', $gen->module);
            $pinCopy = trim(($pin['title'] ?? '')."\n\n".$pinText);
        @endphp
        <div class="flex flex-col rounded-2xl bg-white p-4 ring-1 ring-line">
            <span class="inline-flex w-fit items-center rounded-full bg-gradient-to-r from-red-500 to-orange-500 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Pin {{ $i + 1 }}</span>
            <p class="mt-2 font-semibold text-navy-900">{{ $pin['title'] ?? '' }}</p>
            <p class="mt-1 flex-1 whitespace-pre-line text-sm text-ink-600">{{ $pinText }}</p>
            <div class="mt-3 flex flex-wrap items-center gap-1.5">
                @include('dashboard.offers._disclosure_badge', ['text' => $pin['description'] ?? ''])
                <x-ui.copy-button :text="$pinCopy" size="xs" label="Copy pin" />
            </div>
            <details class="mt-3 rounded-xl bg-surface-muted p-3 text-xs">
                <summary class="cursor-pointer font-medium text-brand-600">Image prompt &amp; alt text</summary>
                <p class="mt-2 text-ink-900"><strong>Image prompt:</strong> {{ $pin['image_prompt'] ?? '' }}</p>
                <p class="mt-1 text-ink-900"><strong>Alt text:</strong> {{ $pin['alt_text'] ?? '' }}</p>
                @if (! empty($pin['image_prompt']))
                    <div class="mt-2"><x-ui.copy-button :text="$pin['image_prompt']" size="xs" label="Copy image prompt" /></div>
                @endif
            </details>
        </div>
    @endforeach
</div>
@if (! empty($gen->output_meta['keywords']))
    <div class="mt-3 flex flex-wrap gap-1.5">
        @foreach ($gen->output_meta['keywords'] as $keyword)
            <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs text-ink-600">{{ $keyword }}</span>
        @endforeach
    </div>
@endif
