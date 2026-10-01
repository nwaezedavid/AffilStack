@php($article = $offer->cloak($gen->output, $gen->module))
<h4 class="font-display text-lg font-semibold text-navy-900">{{ $gen->output_meta['title'] ?? 'Blog article' }}</h4>
@if (! empty($gen->output_meta['meta_description']))
    <p class="mt-1 text-sm text-ink-600"><span class="font-medium text-ink-900">Meta description:</span> {{ $gen->output_meta['meta_description'] }}</p>
@endif
<div class="mt-3 flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output])
    <x-ui.copy-button :text="$article" label="Copy article" />
    @if (! empty($gen->output_meta['title']))
        <x-ui.copy-button :text="$gen->output_meta['title']" label="Copy title" />
    @endif
</div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Read the full article <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 max-h-[32rem] overflow-y-auto whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $article }}</pre>
</details>
