{{-- One generated piece of content on a feature page: header, live status,
     the module's own rendering (generations/{module}), and "localize". --}}
@php
    $moduleLabels = [
        'blog_article' => 'Blog article', 'competitor_angles' => 'Competitor angle scan', 'linkedin_keywords' => 'LinkedIn search keywords',
        'linkedin_dm_sequence' => 'LinkedIn DM sequence', 'linkedin_post' => 'LinkedIn post', 'linkedin_article' => 'LinkedIn article',
        'youtube_script' => 'YouTube video script', 'youtube_metadata' => 'Title, description & tags', 'ugc_angles' => 'UGC angle ideas',
        'ugc_content' => 'UGC script & posting pack', 'ugc_video' => 'UGC video', 'x_thread' => 'X thread', 'tiktok_video' => 'TikTok video package',
        'pinterest_pin' => 'Pinterest pins', 'email_nurture' => 'Email nurture sequence',
    ];
@endphp
<article id="generation-{{ $gen->id }}" class="scroll-mt-24 rounded-3xl border border-line bg-white p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-6">
    <header class="mb-4 flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <span class="text-xs font-semibold uppercase tracking-[0.12em] {{ $tone['text'] ?? 'text-brand-600' }}">{{ $moduleLabels[$gen->module] ?? str_replace('_', ' ', $gen->module) }}</span>
            @if ($gen->target_market)
                @php($marketMeta = config('localization.markets.'.$gen->target_market))
                <span class="rounded-full bg-violet-50 px-2 py-0.5 text-[11px] font-medium text-violet-700 ring-1 ring-violet-200">{{ $marketMeta['flag'] ?? '' }} Localized for {{ $marketMeta['label'] ?? $gen->target_market }}</span>
            @endif
        </div>
        <span class="text-xs text-ink-400">{{ $gen->created_at->diffForHumans() }}</span>
    </header>

    @if ($gen->status === 'queued')
        <div class="flex items-center gap-4 rounded-2xl bg-gradient-to-r from-brand-50 to-white p-4 ring-1 ring-brand-100" role="status">
            <span class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center">
                <span class="absolute inset-0 animate-ping rounded-full bg-brand-500/20"></span>
                <span class="relative inline-flex h-10 w-10 items-center justify-center rounded-full bg-brand-600 text-white"><x-heroicon-m-sparkles class="h-5 w-5 animate-pulse" /></span>
            </span>
            <div class="min-w-0 flex-1">
                <p class="text-sm font-semibold text-navy-900">{{ $gen->module === 'ugc_video' ? 'Rendering your video — this takes a few minutes…' : 'Our AI is writing this now…' }}</p>
                <p class="text-xs text-ink-600">This page updates by itself. You can also leave — we'll notify you when it's ready.</p>
                <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-brand-100">
                    <div class="h-full w-1/3 rounded-full bg-gradient-to-r from-brand-500 to-gold-400" style="animation: affilstack-progress 1.8s ease-in-out infinite"></div>
                </div>
            </div>
        </div>
    @elseif ($gen->status === 'failed')
        <div class="flex items-start gap-3 rounded-2xl bg-red-50 p-4 text-sm text-red-700 ring-1 ring-red-200" role="alert">
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0" />
            <div>
                <p class="font-semibold">This one didn't work.</p>
                <p>{{ $gen->error_message ?: 'Something went wrong.' }} No credits were taken — run it again from the form above.</p>
            </div>
        </div>
    @elseif (view()->exists('dashboard.offers.generations.'.$gen->module))
        @include('dashboard.offers.generations.'.$gen->module)
    @endif

    @if ($gen->status === 'completed' && ! $gen->target_market && in_array($gen->module, config('localization.localizable_modules'), true))
        <footer class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4">
            <form method="POST" action="{{ route('generations.localize', $gen) }}" class="flex flex-wrap items-center gap-2">
                @csrf
                <label for="localize-{{ $gen->id }}" class="text-xs font-medium text-ink-600"><x-heroicon-m-globe-europe-africa class="inline h-4 w-4 text-violet-500" /> Localize for</label>
                <select id="localize-{{ $gen->id }}" name="target_market" required class="rounded-full border border-line bg-white px-3 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <option value="">Choose a market…</option>
                    @foreach (config('localization.markets') as $code => $marketOption)
                        <option value="{{ $code }}">{{ $marketOption['flag'] }} {{ $marketOption['label'] }}</option>
                    @endforeach
                </select>
                <x-ui.button variant="secondary" size="sm">Localize ({{ config('credits.costs.localization') }})</x-ui.button>
            </form>
        </footer>
    @endif
</article>
