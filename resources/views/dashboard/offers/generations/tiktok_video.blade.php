@php
    $script = $offer->cloak($gen->output_meta['script'] ?? '', $gen->module, generation: $gen, withDisclosure: false);
    $caption = $offer->cloak($gen->output_meta['caption'] ?? '', $gen->module, generation: $gen);
    $hashtags = implode(' ', $gen->output_meta['hashtags'] ?? []);
@endphp
<div class="grid gap-4 lg:grid-cols-2">
    <div>
        <div class="flex items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Script</p>
            <x-ui.copy-button :text="$script" size="xs" />
        </div>
        <pre class="mt-2 max-h-80 overflow-y-auto whitespace-pre-wrap rounded-2xl bg-surface-muted p-4 font-sans text-sm leading-relaxed text-ink-900">{{ $script }}</pre>
    </div>
    <div class="space-y-3">
        @if (! empty($gen->output_meta['on_screen_text']))
            <div>
                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">On-screen text</p>
                <ol class="space-y-1.5">
                    @foreach ($gen->output_meta['on_screen_text'] as $cue)
                        <li class="flex gap-2 rounded-xl bg-fuchsia-50 px-3 py-2 text-sm text-ink-900"><span class="shrink-0 font-mono text-xs text-fuchsia-700">{{ $cue['timing'] ?? '' }}</span> {{ $cue['text'] ?? '' }}</li>
                    @endforeach
                </ol>
            </div>
        @endif
        <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-ink-400">Caption</p>
                <div class="flex items-center gap-1.5">
                    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['caption'] ?? ''])
                    <x-ui.copy-button :text="trim($caption.' '.$hashtags)" size="xs" label="Copy caption + tags" />
                </div>
            </div>
            <p class="mt-2 whitespace-pre-line text-sm text-ink-900">{{ $caption }}</p>
            @if ($hashtags !== '')
                <p class="mt-2 text-sm text-fuchsia-700">{{ $hashtags }}</p>
            @endif
        </div>
    </div>
</div>
