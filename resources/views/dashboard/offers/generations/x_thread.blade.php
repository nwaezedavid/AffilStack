@php
    $tweets = collect($gen->output_meta['thread'] ?? [])->values()->map(fn ($tweet, $i) => $offer->cloak($tweet['text'] ?? '', $gen->module, generation: $gen, withDisclosure: $i === 0));
    $threadText = $tweets->implode("\n\n");
@endphp
<div class="flex flex-wrap items-center gap-2">
    @include('dashboard.offers._disclosure_badge', ['text' => $gen->output_meta['thread'][0]['text'] ?? ''])
    <x-ui.copy-button :text="$threadText" label="Copy whole thread" />
    @if (! empty($gen->output_meta['best_posting_time']))
        <span class="inline-flex items-center gap-1 text-xs text-ink-600"><x-heroicon-m-clock class="h-3.5 w-3.5" /> Best time: {{ $gen->output_meta['best_posting_time'] }}</span>
    @endif
</div>
{{-- Disclosure goes on the first post only — FTC-style guidance wants it
     "above the fold", not repeated (or missed) down a thread. --}}
<ol class="mt-3 space-y-2">
    @foreach ($tweets as $i => $tweet)
        <li class="relative rounded-2xl bg-white p-3.5 ring-1 ring-line">
            <div class="flex items-center justify-between gap-2">
                <span class="inline-flex h-6 min-w-6 items-center justify-center rounded-full bg-slate-900 px-1.5 text-[11px] font-semibold text-white">{{ $i + 1 }}</span>
                <x-ui.copy-button :text="$tweet" size="xs" />
            </div>
            <p class="mt-2 whitespace-pre-line text-sm text-ink-900">{{ $tweet }}</p>
        </li>
    @endforeach
</ol>
@if (! empty($gen->output_meta['hook_variants']))
    <div class="mt-3 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">Alternative opening hooks to test</p>
        <ul class="space-y-1.5">
            @foreach ($gen->output_meta['hook_variants'] as $hook)
                <li class="flex items-start justify-between gap-2 text-sm text-ink-900"><span>{{ $hook }}</span> <x-ui.copy-button :text="$hook" size="xs" /></li>
            @endforeach
        </ul>
    </div>
@endif
