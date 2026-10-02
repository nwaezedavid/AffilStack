@php
    $sizeOptions = config('affiliate_kit.sizes');
    $groups = config('affiliate_kit.size_groups');
    $designs = $banners->unique('design')->pluck('design_name', 'design');
    $uploadCount = $banners->where('kind', 'upload')->count();
    $bannerData = $banners->keyBy('key')->map(fn ($b) => [
        'title' => $b['title'], 'size' => $b['size'], 'width' => $b['width'], 'height' => $b['height'],
        'preview' => $b['preview_url'], 'snippets' => $b['snippets'], 'link' => $b['link'],
    ]);
    $performing = $banners->filter(fn ($b) => $b['stats']['impressions'] > 0 || $b['stats']['clicks'] > 0)
        ->sortByDesc(fn ($b) => $b['stats']['clicks'] * 100000 + $b['stats']['impressions']);
@endphp

<div class="grid gap-4 lg:grid-cols-3 mb-6">
    <div class="lg:col-span-2 rounded-2xl border border-line bg-surface p-5 sm:p-6">
        <h3 class="font-display font-semibold text-base text-navy-900">Banners that always credit you</h3>
        <p class="mt-1 text-sm text-ink-600">Every banner below already contains your tracking code. Pick a size, click <strong>Get code</strong> and paste it anywhere: your website, blog sidebar, newsletter or forum signature.</p>
        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['icon' => 'code-bracket', 'title' => 'HTML', 'body' => 'Works everywhere, including email and most forums.'],
                ['icon' => 'window', 'title' => 'iframe', 'body' => 'Responsive, counts views, and we can update the artwork for you.'],
                ['icon' => 'bolt', 'title' => 'One-line JS', 'body' => 'Auto-rotates designs so your audience never sees the same ad twice.'],
            ] as $option)
                <div class="rounded-xl bg-surface-muted p-3">
                    <p class="flex items-center gap-1.5 text-sm font-semibold text-navy-900"><x-dynamic-component :component="'heroicon-o-'.$option['icon']" class="h-4 w-4 text-brand-600" /> {{ $option['title'] }}</p>
                    <p class="mt-1 text-xs text-ink-600">{{ $option['body'] }}</p>
                </div>
            @endforeach
        </div>
    </div>
    <div class="rounded-2xl border border-gold-300/60 bg-gradient-to-br from-gold-300/20 via-white to-white p-5 sm:p-6">
        <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-gold-600">Tip</p>
        <p class="mt-1 text-sm text-ink-900">Banners placed <strong>inside</strong> your content (after the first paragraph of a review or tutorial) usually get far more clicks than ones in a footer.</p>
        <p class="mt-3 text-sm text-ink-600">Need a size that isn't here? Ask support and we'll add it to the kit.</p>
    </div>
</div>

{{-- Filters --}}
<div class="mb-5 flex flex-col gap-3 xl:flex-row xl:items-start xl:justify-between">
    <div>
        <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filter banners by size">
            <button type="button" data-banner-size="all" class="banner-size-chip shrink-0 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-line bg-white text-ink-600 hover:bg-surface-muted aria-pressed:bg-navy-900 aria-pressed:text-white aria-pressed:ring-navy-900" aria-pressed="false">All sizes</button>
            @foreach ($sizeOptions as $key => $size)
                <button type="button" data-banner-size="{{ $key }}" class="banner-size-chip shrink-0 rounded-full px-3 py-1.5 text-xs font-medium ring-1 ring-line bg-white text-ink-600 hover:bg-surface-muted aria-pressed:bg-navy-900 aria-pressed:text-white aria-pressed:ring-navy-900" aria-pressed="{{ $key === '300x250' ? 'true' : 'false' }}" title="{{ $size['label'] }}">{{ str_replace('x', '×', $key) }}</button>
            @endforeach
        </div>
    </div>
    <div class="flex shrink-0 items-center gap-2">
        <label for="banner-design-filter" class="text-xs font-medium text-ink-600">Design</label>
        <select id="banner-design-filter" class="rounded-full border border-line bg-white px-3 py-1.5 text-xs text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            <option value="all">All designs</option>
            @foreach ($designs as $design => $name)
                <option value="{{ $design }}">{{ $name }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($uploadCount > 0)
    <p class="mb-3 flex items-center gap-1.5 text-xs font-medium text-gold-600"><x-heroicon-s-star class="h-4 w-4" /> Featured banners from the AffilStack team are shown first.</p>
@endif

<div id="banner-grid" class="grid gap-5 sm:grid-cols-2 xl:grid-cols-3">
    @foreach ($banners as $banner)
        <article class="banner-card flex flex-col overflow-hidden rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)] {{ $banner['size'] === '300x250' ? '' : 'hidden' }}"
                 data-size="{{ $banner['size'] }}" data-design="{{ $banner['design'] }}" data-group="{{ $banner['group'] }}">
            <div class="relative flex h-60 items-center justify-center bg-[radial-gradient(circle_at_1px_1px,#dfe3ee_1px,transparent_0)] [background-size:14px_14px] bg-surface-muted p-4">
                @if ($banner['kind'] === 'upload')
                    <span class="absolute left-3 top-3 inline-flex items-center gap-1 rounded-full bg-gold-400 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-navy-950"><x-heroicon-s-star class="h-3 w-3" /> Featured</span>
                @endif
                <img src="{{ $banner['preview_url'] }}" alt="{{ $banner['alt'] }}" width="{{ $banner['width'] }}" height="{{ $banner['height'] }}" loading="lazy"
                     class="max-h-full max-w-full w-auto h-auto rounded-md shadow-md object-contain">
            </div>
            <div class="flex flex-1 flex-col p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <h4 class="font-display text-sm font-semibold text-navy-900 truncate">{{ $banner['design_name'] }}</h4>
                        <p class="text-xs text-ink-600">{{ str_replace('x', '×', $banner['size']) }} · {{ $banner['size_label'] }}</p>
                    </div>
                    <p class="shrink-0 text-right text-[11px] text-ink-400 tabular-nums" title="Views counted from iframe/JS embeds; clicks from every embed type">
                        <span class="block"><span class="font-semibold text-ink-900">{{ number_format($banner['stats']['impressions']) }}</span> views</span>
                        <span class="block"><span class="font-semibold text-ink-900">{{ number_format($banner['stats']['clicks']) }}</span> clicks</span>
                    </p>
                </div>
                <div class="mt-4 flex flex-wrap gap-2">
                    <x-ui.button type="button" size="sm" variant="primary" icon="code-bracket" data-banner-open="{{ $banner['key'] }}">Get code</x-ui.button>
                    @if ($banner['kind'] === 'upload')
                        <x-ui.button size="sm" variant="secondary" icon="arrow-down-tray" :href="$banner['download_url']" download>Download</x-ui.button>
                    @else
                        <x-ui.button size="sm" variant="secondary" icon="arrow-down-tray" :href="$banner['svg_url'].'?download=1'">SVG</x-ui.button>
                        @if ($banner['png_url'])
                            <x-ui.button size="sm" variant="secondary" icon="arrow-down-tray" :href="$banner['png_url'].'?download=1'">PNG</x-ui.button>
                        @endif
                    @endif
                </div>
            </div>
        </article>
    @endforeach
</div>
<p id="banner-empty" class="hidden rounded-2xl border border-dashed border-line bg-surface p-8 text-center text-sm text-ink-600">No banners match this filter.</p>

<x-ui.card class="mt-8" title="Banner performance" description="Views are counted from the iframe and JS embeds (plain HTML/email can't report views). Clicks are counted for every embed type." icon="chart-bar">
    @if ($performing->isEmpty())
        <p class="text-sm text-ink-600">No banner activity yet. Once your embedded banners are seen or clicked, each one's numbers appear here so you can keep the winners.</p>
    @else
        <div class="-mx-5 sm:-mx-6 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-5 sm:px-6 py-2.5">Banner</th>
                        <th class="text-right px-4 py-2.5">Views</th>
                        <th class="text-right px-4 py-2.5">Clicks</th>
                        <th class="text-right px-5 sm:px-6 py-2.5">Click rate</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($performing as $banner)
                        <tr>
                            <td class="px-5 sm:px-6 py-3 text-ink-900">{{ $banner['title'] }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($banner['stats']['impressions']) }}</td>
                            <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($banner['stats']['clicks']) }}</td>
                            <td class="px-5 sm:px-6 py-3 text-right font-mono tabular-nums">{{ $banner['stats']['ctr'] !== null ? $banner['stats']['ctr'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</x-ui.card>

<x-ui.next-step class="mt-6" :href="route('referrals.index', ['tab' => 'emails'])" action="Get email swipes" data-tab-link="emails">
    Banner placed? Send one of the ready-made emails to your list as well. Email readers convert well because they already trust you.
</x-ui.next-step>

{{-- Embed-code dialog, filled from $bannerData by the script below. --}}
<dialog id="banner-dialog" class="m-auto w-[min(720px,calc(100vw-2rem))] max-h-[calc(100vh-2rem)] rounded-2xl border border-line bg-surface p-0 shadow-2xl backdrop:bg-navy-950/60" aria-labelledby="banner-dialog-title">
    <div class="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
        <div>
            <p class="text-[11px] font-mono uppercase tracking-[0.16em] text-gold-600">Embed code</p>
            <h3 id="banner-dialog-title" class="font-display text-base font-semibold text-navy-900">Banner</h3>
        </div>
        <button type="button" data-banner-close class="rounded-full p-1.5 text-ink-400 hover:bg-surface-muted hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500" aria-label="Close">
            <x-heroicon-o-x-mark class="h-5 w-5" />
        </button>
    </div>
    <div class="overflow-y-auto px-5 py-4" style="max-height: calc(100vh - 10rem)">
        <div class="flex justify-center rounded-xl bg-surface-muted p-4">
            <img id="banner-dialog-preview" src="" alt="" class="max-h-56 w-auto max-w-full rounded-md shadow-md">
        </div>

        <div class="mt-4 flex gap-1 rounded-xl bg-surface-muted p-1" role="tablist" aria-label="Embed type">
            @foreach (['html' => 'HTML (any site & email)', 'iframe' => 'iframe', 'script' => 'JavaScript'] as $kind => $label)
                <button type="button" role="tab" data-embed-kind="{{ $kind }}" aria-selected="{{ $kind === 'html' ? 'true' : 'false' }}"
                        class="flex-1 rounded-lg px-3 py-1.5 text-xs font-medium text-ink-600 aria-selected:bg-white aria-selected:text-navy-900 aria-selected:shadow-sm focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">{{ $label }}</button>
            @endforeach
        </div>

        <p id="banner-embed-help" class="mt-3 text-xs text-ink-600"></p>
        <label class="mt-2 hidden items-center gap-2 text-xs text-ink-900" id="banner-rotate-wrap">
            <input type="checkbox" id="banner-rotate" class="rounded border-line text-brand-600 focus:ring-brand-500">
            Rotate between all {{ count(config('affiliate_kit.designs')) }} designs at this size
        </label>
        <label for="banner-embed-code" class="sr-only">Embed code</label>
        <textarea id="banner-embed-code" readonly rows="5" class="mt-2 w-full rounded-xl border border-line bg-navy-950 p-3 font-mono text-xs leading-relaxed text-emerald-200 focus:outline-none focus:ring-2 focus:ring-brand-500" onclick="this.select()"></textarea>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <x-ui.copy-button target="banner-embed-code" label="Copy code" variant="primary" size="md" />
            <p class="text-xs text-ink-400">Your code: <span class="font-mono text-ink-900">{{ $user->referralCode() }}</span></p>
        </div>

        <div class="mt-5 border-t border-line pt-4">
            <label for="banner-direct-link" class="text-xs font-medium text-ink-900">Tracked link for this banner (if you upload the image yourself)</label>
            <div class="mt-1.5 flex flex-col gap-2 sm:flex-row">
                <input id="banner-direct-link" type="text" readonly class="w-full min-w-0 rounded-xl border border-line bg-surface-muted px-3 py-2 font-mono text-xs text-navy-900" onclick="this.select()">
                <x-ui.copy-button target="banner-direct-link" label="Copy link" class="shrink-0" />
            </div>
        </div>
    </div>
</dialog>

@push('scripts')
    <script>
        (function () {
            var banners = @json($bannerData);
            var grid = document.getElementById('banner-grid');
            var empty = document.getElementById('banner-empty');
            var designSelect = document.getElementById('banner-design-filter');
            var chips = document.querySelectorAll('[data-banner-size]');
            var currentSize = '300x250';

            function applyFilters() {
                var design = designSelect.value;
                var shown = 0;
                grid.querySelectorAll('.banner-card').forEach(function (card) {
                    var visible = (currentSize === 'all' || card.dataset.size === currentSize || card.dataset.group === currentSize)
                        && (design === 'all' || card.dataset.design === design);
                    card.classList.toggle('hidden', !visible);
                    if (visible) { shown++; }
                });
                empty.classList.toggle('hidden', shown > 0);
                chips.forEach(function (chip) { chip.setAttribute('aria-pressed', chip.dataset.bannerSize === currentSize ? 'true' : 'false'); });
            }

            window.affilstackFilterBanners = function (size) { currentSize = size; applyFilters(); };
            chips.forEach(function (chip) { chip.addEventListener('click', function () { currentSize = chip.dataset.bannerSize; applyFilters(); }); });
            designSelect.addEventListener('change', applyFilters);

            var dialog = document.getElementById('banner-dialog');
            var code = document.getElementById('banner-embed-code');
            var help = document.getElementById('banner-embed-help');
            var rotate = document.getElementById('banner-rotate');
            var rotateWrap = document.getElementById('banner-rotate-wrap');
            var helpText = {
                html: 'Plain HTML: works on any website, in email newsletters and in most forums. Clicks are tracked; views are not.',
                iframe: 'Responsive iframe: shrinks to fit on mobile, counts views and clicks, and always opens AffilStack in a new tab.',
                script: 'One line of JavaScript: drops in a responsive banner and can rotate designs automatically. Views and clicks are tracked.'
            };
            var current = null;
            var kind = 'html';

            function render() {
                if (!current) { return; }
                var snippets = current.snippets;
                code.value = kind === 'script' ? (rotate.checked ? snippets.script_rotating : snippets.script) : snippets[kind];
                help.textContent = helpText[kind];
                rotateWrap.classList.toggle('hidden', kind !== 'script');
                rotateWrap.classList.toggle('flex', kind === 'script');
                dialog.querySelectorAll('[data-embed-kind]').forEach(function (tab) { tab.setAttribute('aria-selected', tab.dataset.embedKind === kind ? 'true' : 'false'); });
            }

            document.addEventListener('click', function (event) {
                var opener = event.target.closest('[data-banner-open]');
                if (opener) {
                    current = banners[opener.dataset.bannerOpen];
                    if (!current) { return; }
                    document.getElementById('banner-dialog-title').textContent = current.title;
                    var preview = document.getElementById('banner-dialog-preview');
                    preview.src = current.preview;
                    preview.alt = current.title;
                    document.getElementById('banner-direct-link').value = current.link;
                    kind = 'html';
                    rotate.checked = false;
                    render();
                    dialog.showModal();
                    return;
                }
                var tab = event.target.closest('[data-embed-kind]');
                if (tab) { kind = tab.dataset.embedKind; render(); return; }
                if (event.target.closest('[data-banner-close]') || event.target === dialog) { dialog.close(); }
            });
            rotate.addEventListener('change', render);
        })();
    </script>
@endpush
