<div class="grid gap-5 md:grid-cols-[minmax(0,15rem)_1fr]">
    <video controls preload="metadata" playsinline class="aspect-[9/16] w-full max-w-[15rem] rounded-2xl border border-line bg-navy-950 shadow-lg" src="{{ $gen->output }}"></video>
    <div>
        <p class="font-display text-lg font-semibold text-navy-900">Your video is ready</p>
        <p class="mt-1 text-sm text-ink-600">Vertical 9:16 — made for TikTok, Instagram Reels and YouTube Shorts.</p>
        <div class="mt-3 flex flex-wrap gap-2">
            <x-ui.button :href="$gen->output" variant="primary" icon="arrow-down-tray" download>Download video</x-ui.button>
            @if (! empty($gen->input['content_generation_id']))
                <x-ui.button :href="'#posting-pack-'.$gen->input['content_generation_id']" variant="secondary" icon="clipboard-document">Captions to post with</x-ui.button>
            @endif
        </div>
        <div class="mt-4 border-t border-line pt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-400">Publish directly</p>
            <div class="flex flex-wrap gap-2">
                @foreach ($publishProviders as $key => $provider)
                    @php
                        $published = $gen->output_meta['published'][$key] ?? null;
                        $connection = $socialConnections->get($key);
                    @endphp
                    @if ($published)
                        <a href="{{ $published['url'] }}" target="_blank" rel="noopener" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-medium text-emerald-700 ring-1 ring-emerald-200">
                            <x-ui.brand-icon :name="$key" class="h-3.5 w-3.5" /> Published to {{ $provider['label'] }} — view
                        </a>
                    @elseif ($connection && $provider['approved'])
                        <form method="POST" action="{{ route('generations.publish', [$gen, $key]) }}">
                            @csrf
                            <button class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted transition">
                                <x-ui.brand-icon :name="$key" class="h-3.5 w-3.5" /> Publish to {{ $provider['label'] }}
                            </button>
                        </form>
                    @elseif ($connection)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1.5 text-xs text-ink-600" title="Awaiting {{ $provider['label'] }}'s approval of AffilStack for publishing">
                            <x-ui.brand-icon :name="$key" class="h-3.5 w-3.5" /> {{ $provider['label'] }}: awaiting approval
                        </span>
                    @else
                        <a href="{{ route('social-connections.index') }}" class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs text-ink-600 ring-1 ring-line hover:bg-surface-muted transition">
                            <x-ui.brand-icon :name="$key" class="h-3.5 w-3.5" /> Connect {{ $provider['label'] }} to publish
                        </a>
                    @endif
                @endforeach
            </div>
        </div>
        @if (! empty($gen->input['video_script']))
            <details class="mt-4 rounded-2xl bg-surface-muted p-3 text-sm">
                <summary class="cursor-pointer font-medium text-brand-600">What the presenter says{{ ! empty($gen->input['script_edited']) ? ' (your edited script)' : '' }}</summary>
                <p class="mt-2 whitespace-pre-line text-ink-900">{{ $gen->input['video_script'] }}</p>
            </details>
        @endif
    </div>
</div>
