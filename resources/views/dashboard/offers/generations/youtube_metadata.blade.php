@php($description = $offer->cloak($gen->output_meta['description'] ?? '', $gen->module, generation: $gen))
<p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Title options — pick one</p>
<ul class="space-y-1.5">
    @foreach ($gen->output_meta['title_options'] ?? [] as $title)
        <li class="flex items-center justify-between gap-2 rounded-xl bg-white px-3 py-2 ring-1 ring-line">
            <span class="text-sm font-medium text-navy-900">{{ $title }}</span>
            <x-ui.copy-button :text="$title" size="xs" />
        </li>
    @endforeach
</ul>
<div class="mt-3 flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['description'] ?? ''])
    <x-ui.copy-button :text="$description" label="Copy description" />
    @if (! empty($gen->output_meta['tags']))
        <x-ui.copy-button :text="implode(', ', $gen->output_meta['tags'])" label="Copy tags" />
    @endif
</div>
<details class="group mt-3 rounded-2xl bg-surface-muted p-4">
    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-brand-600">Read the description <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" /></summary>
    <pre class="mt-3 whitespace-pre-wrap font-sans text-sm leading-relaxed text-ink-900">{{ $description }}</pre>
</details>
<div class="mt-3 grid gap-3 sm:grid-cols-2">
    @foreach (['keywords' => 'Keywords', 'tags' => 'Tags'] as $key => $label)
        @if (! empty($gen->output_meta[$key]))
            <div>
                <p class="mb-1.5 text-xs font-semibold uppercase tracking-wide text-ink-400">{{ $label }}</p>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($gen->output_meta[$key] as $chip)
                        <span class="rounded-full bg-red-50 px-2.5 py-1 text-xs text-red-800 ring-1 ring-red-100">{{ $chip }}</span>
                    @endforeach
                </div>
            </div>
        @endif
    @endforeach
</div>
<dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
    @if (! empty($gen->output_meta['category']))
        <div class="rounded-xl bg-white p-3 ring-1 ring-line"><dt class="font-semibold uppercase tracking-wide text-ink-400">Category</dt><dd class="mt-1 text-ink-900">{{ $gen->output_meta['category'] }}</dd></div>
    @endif
    @if (! empty($gen->output_meta['thumbnail_prompt']))
        <div class="rounded-xl bg-white p-3 ring-1 ring-line"><dt class="font-semibold uppercase tracking-wide text-ink-400">Thumbnail prompt</dt><dd class="mt-1 text-ink-900">{{ $gen->output_meta['thumbnail_prompt'] }}</dd></div>
    @endif
</dl>
