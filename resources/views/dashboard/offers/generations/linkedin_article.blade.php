@php($article = $offer->cloak($gen->output_meta['article_markdown'] ?? '', $gen->module, generation: $gen))
<h4 class="font-display text-lg font-semibold text-navy-900">{{ $gen->output_meta['headline'] ?? 'LinkedIn article' }}</h4>
<div class="mt-3 flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['article_markdown'] ?? ''])
    <x-ui.copy-button :text="$article" label="Copy article" />
    <a href="{{ route('generations.linkedin.export', $gen) }}" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50"><x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" /> Export .txt</a>
</div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Read the full article <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 max-h-[32rem] overflow-y-auto whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $article }}</pre>
</details>
