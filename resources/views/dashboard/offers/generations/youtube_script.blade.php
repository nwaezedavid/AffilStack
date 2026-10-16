@php($script = $offer->cloak($gen->output_meta['script_markdown'] ?? '', $gen->module, generation: $gen, withDisclosure: false))
<h4 class="font-display text-lg font-semibold text-navy-900">{{ $gen->output_meta['working_title'] ?? 'Video script' }}</h4>
<p class="mt-1 flex flex-wrap items-center gap-2 text-xs text-ink-600">
    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 px-2 py-0.5 font-medium text-red-700"><x-heroicon-m-clock class="h-3.5 w-3.5" /> ~{{ $gen->output_meta['estimated_length_minutes'] ?? '?' }} min</span>
    @if (! empty($gen->output_meta['hook']))
        <span><strong class="text-ink-900">Hook:</strong> {{ $gen->output_meta['hook'] }}</span>
    @endif
</p>
<div class="mt-3"><x-ui.copy-button :text="$script" label="Copy script" /></div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Read the full script <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 max-h-[32rem] overflow-y-auto whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $script }}</pre>
</details>
@if (! empty($gen->output_meta['b_roll_suggestions']))
    <div class="mt-3">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">B-roll ideas</p>
        <ul class="grid gap-1.5 text-sm text-ink-900 sm:grid-cols-2">
            @foreach ($gen->output_meta['b_roll_suggestions'] as $idea)
                <li class="flex gap-2 rounded-xl bg-white p-2.5 ring-1 ring-line"><x-heroicon-m-film class="mt-0.5 h-4 w-4 shrink-0 text-red-500" /> {{ $idea }}</li>
            @endforeach
        </ul>
    </div>
@endif
