<div class="grid gap-4 sm:grid-cols-2">
    @foreach (['search_keywords' => ['Search keywords', 'people'], 'job_titles' => ['Job titles', 'people']] as $key => [$label, $type])
        <div>
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $label }}</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($gen->output_meta[$key] ?? [] as $keyword)
                    <a href="{{ \App\Support\ResearchPresenter::linkedinUrl($type, $keyword) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center gap-1 rounded-full bg-sky-50 px-2.5 py-1 text-xs font-medium text-sky-800 ring-1 ring-sky-100 hover:bg-sky-100">
                        {{ $keyword }} <x-heroicon-m-arrow-top-right-on-square class="h-3 w-3" />
                    </a>
                @endforeach
            </div>
        </div>
    @endforeach
    @if (! empty($gen->output_meta['boolean_search_strings']))
        <div class="sm:col-span-2">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Boolean search strings</p>
            <div class="space-y-1.5">
                @foreach ($gen->output_meta['boolean_search_strings'] as $bs)
                    <div class="flex items-center justify-between gap-2 rounded-xl bg-navy-950 px-3 py-2">
                        <code class="min-w-0 break-all text-xs text-emerald-200">{{ $bs }}</code>
                        <x-ui.copy-button :text="$bs" size="xs" tone="light" />
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
