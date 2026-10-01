<ol class="grid gap-2.5 md:grid-cols-2">
    @foreach ($gen->output_meta['angles'] ?? [] as $index => $angle)
        <li class="rounded-2xl bg-white p-3.5 ring-1 ring-line">
            <p class="flex flex-wrap items-center gap-1.5 font-semibold text-navy-900">
                {{ $angle['name'] ?? '' }}
                <span class="rounded-full bg-rose-50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-rose-700">{{ $angle['format'] ?? '' }}</span>
            </p>
            <p class="mt-1 text-sm text-ink-900">&ldquo;{{ $angle['hook_idea'] ?? '' }}&rdquo;</p>
            <p class="mt-1 text-xs text-ink-600">{{ $angle['why_it_works'] ?? '' }}</p>
        </li>
    @endforeach
</ol>
<p class="mt-3 text-xs text-ink-400">Pick one of these in step 1 above to write its script.</p>
