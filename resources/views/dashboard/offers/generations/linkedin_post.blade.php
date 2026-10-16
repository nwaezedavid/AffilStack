@php($post = $offer->cloak($gen->output_meta['post_text'] ?? '', $gen->module, generation: $gen))
<div class="rounded-2xl bg-surface-muted p-4">
    <p class="whitespace-pre-line text-sm leading-relaxed text-ink-900">{{ $post }}</p>
</div>
<div class="mt-3 flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['post_text'] ?? ''])
    <x-ui.copy-button :text="$post" label="Copy post" />
    <a href="{{ route('generations.linkedin.export', $gen) }}" class="inline-flex items-center gap-1 rounded-full px-2.5 py-1.5 text-xs font-medium text-brand-600 hover:bg-brand-50"><x-heroicon-m-arrow-down-tray class="h-3.5 w-3.5" /> Export .txt</a>
</div>
<dl class="mt-3 grid gap-2 text-xs sm:grid-cols-2">
    @if (! empty($gen->output_meta['image_prompt']))
        <div class="rounded-xl bg-white p-3 ring-1 ring-line"><dt class="font-semibold text-ink-400 uppercase tracking-wide">Image prompt</dt><dd class="mt-1 text-ink-900">{{ $gen->output_meta['image_prompt'] }}</dd></div>
    @endif
    @if (! empty($gen->output_meta['best_posting_time']))
        <div class="rounded-xl bg-white p-3 ring-1 ring-line"><dt class="font-semibold text-ink-400 uppercase tracking-wide">Best time to post</dt><dd class="mt-1 text-ink-900">{{ $gen->output_meta['best_posting_time'] }}</dd></div>
    @endif
</dl>
