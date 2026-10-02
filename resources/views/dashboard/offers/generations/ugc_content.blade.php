@php
    $ugcScript = $offer->cloak($gen->output_meta['script'] ?? '', $gen->module, withDisclosure: false);
    $angleName = null;
    if (isset($gen->input['angles_generation_id'], $gen->input['angle_index'])) {
        $angleName = $offer->generations->firstWhere('id', $gen->input['angles_generation_id'])?->output_meta['angles'][$gen->input['angle_index']]['name'] ?? null;
    }
@endphp
<div class="flex flex-wrap items-center justify-between gap-2">
    <p class="text-sm text-ink-600">{{ $angleName ? 'Angle: '.$angleName : 'UGC script' }}</p>
    <div class="flex flex-wrap gap-1.5">
        <x-ui.copy-button :text="$ugcScript" label="Copy filming script" />
        <x-ui.button :href="route('offers.feature', [$offer, 'ugc', 'script' => $gen->id]).'#step-video'" variant="ghost" size="sm" icon="video-camera">Edit &amp; make video</x-ui.button>
    </div>
</div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Filming script (beat by beat) <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $ugcScript }}</pre>
</details>
@if (! empty($gen->output_meta['on_screen_text_ideas']))
    <div class="mt-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">On-screen text ideas</p>
        <div class="flex flex-wrap gap-1.5">
            @foreach ($gen->output_meta['on_screen_text_ideas'] as $idea)
                <span class="rounded-full bg-rose-50 px-2.5 py-1 text-xs text-rose-800 ring-1 ring-rose-100">{{ $idea }}</span>
            @endforeach
        </div>
    </div>
@endif
@if (! empty($gen->output_meta['platforms']))
    <div id="posting-pack-{{ $gen->id }}" class="mt-4">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Posting pack — captions for each platform</p>
        <div class="grid gap-2.5 md:grid-cols-2">
            @foreach ($gen->output_meta['platforms'] as $platform)
                @php
                    $caption = $offer->cloak($platform['caption'] ?? '', $gen->module);
                    $tags = implode(' ', array_map(fn ($tag) => str_starts_with($tag, '#') ? $tag : '#'.$tag, $platform['tags'] ?? []));
                @endphp
                <div class="rounded-2xl bg-white p-4 ring-1 ring-line">
                    <div class="flex items-center justify-between gap-2">
                        <p class="font-semibold text-navy-900">{{ $platform['platform'] ?? '' }}</p>
                        <x-ui.copy-button :text="trim($caption.' '.$tags)" size="xs" label="Copy caption" />
                    </div>
                    @if (! empty($platform['title']))
                        <p class="mt-1 text-sm font-medium text-ink-900">{{ $platform['title'] }}</p>
                    @endif
                    <p class="mt-1 whitespace-pre-line text-sm text-ink-600">{{ $caption }}</p>
                    @if ($tags !== '')
                        <p class="mt-1.5 text-xs text-rose-700">{{ $tags }}</p>
                    @endif
                    <div class="mt-2 flex flex-wrap items-center gap-2">
                        @include('dashboard.offers._disclosure_badge', ['text' => $platform['caption'] ?? ''])
                    </div>
                    @if (! empty($platform['posting_tip']))
                        <p class="mt-2 flex gap-1.5 text-xs text-ink-600"><x-heroicon-m-light-bulb class="h-4 w-4 shrink-0 text-gold-500" /> {{ $platform['posting_tip'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
@endif
