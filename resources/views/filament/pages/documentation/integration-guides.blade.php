<x-filament-panels::page>
    @include('filament.pages.documentation.guides._styles')

    @php
        $registry = $this->registry();
        $phases = $this->phases();
        $summary = $this->summary($phases);
        $index = $this->searchIndex();
        $brandIcons = config('brand_icons');
        $percent = $summary['total'] ? (int) round($summary['working'] / $summary['total'] * 100) : 0;
    @endphp

    <script type="application/json" id="afs-guide-index">{!! json_encode($index, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) !!}</script>
    <script>
        window.afsGuideSearch = function () {
            const escapeHtml = (s) => String(s).replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
            const escapeRegExp = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

            return {
                q: '',
                index: JSON.parse(document.getElementById('afs-guide-index')?.textContent || '[]'),
                get results() {
                    const q = this.q.trim().toLowerCase();
                    if (q.length < 2) { return []; }
                    const words = q.split(/\s+/);

                    return this.index
                        .map((g) => {
                            const hay = (g.title + ' ' + g.text).toLowerCase();
                            if (! words.every((w) => hay.includes(w))) { return null; }
                            const at = g.text.toLowerCase().indexOf(words[0]);
                            const start = Math.max(0, at - 70);
                            const snippet = at >= 0 ? (start > 0 ? '…' : '') + g.text.slice(start, at + 160) + '…' : g.text.slice(0, 160) + '…';
                            const score = (g.title.toLowerCase().includes(q) ? 10 : 0) + words.length;

                            return { ...g, snippet, score };
                        })
                        .filter(Boolean)
                        .sort((a, b) => b.score - a.score)
                        .slice(0, 12);
                },
                mark(text) {
                    let out = escapeHtml(text);
                    this.q.trim().split(/\s+/).filter((w) => w.length > 1).forEach((w) => {
                        out = out.replace(new RegExp('(' + escapeRegExp(escapeHtml(w)) + ')', 'ig'), '<mark>$1</mark>');
                    });

                    return out;
                },
            };
        };
    </script>

    <div class="afs-g" x-data="afsGuideSearch()">
        <div class="afs-g-hero">
            <div class="afs-g-hero-row">
                <div>
                    <span class="afs-g-eyebrow"><x-filament::icon icon="heroicon-o-rocket-launch" style="width:0.9rem;height:0.9rem" /> Setup checklist · last reviewed {{ $registry->lastReviewed() }}</span>
                    <h2>Connect AffilStack to everything it needs — in the right order</h2>
                    <p>Every service AffilStack talks to, from email and payments to Google, TikTok and the Chrome Web Store. Work top to bottom: each card shows what it unlocks, what it costs, how long it takes and whether it's working right now. Open a card for exact, copy-and-paste steps.</p>
                    <div class="afs-g-progress" aria-hidden="true"><span style="width: {{ $percent }}%"></span></div>
                    <div class="afs-g-hero-stats">
                        <div class="afs-g-hero-stat"><strong>{{ $summary['working'] }} / {{ $summary['total'] }}</strong>working or customer-side</div>
                        <div class="afs-g-hero-stat"><strong>{{ $summary['required_open'] }}</strong>required still open</div>
                        <div class="afs-g-hero-stat"><strong>{{ $summary['attention'] }}</strong>failing checks</div>
                    </div>
                </div>
                <a href="{{ $this->connectionsHealthUrl() }}" class="afs-g-btn afs-g-btn--light">
                    <x-filament::icon icon="heroicon-o-heart" /> Run live checks
                </a>
            </div>
        </div>

        @unless ($registry->siteUrlLooksLive())
            <div class="afs-g-callout afs-g-callout--warning" role="note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                <div>
                    <span class="afs-g-callout-title">This copy of AffilStack runs at {{ \App\Support\SiteDomains::publicUrl() }}</span>
                    Every address in these guides (redirect URIs, webhook URLs) is built from this site's address. Google, Meta, TikTok and the payment gateways only accept the live <code>https://</code> address — copy them from the guides on the live site.
                </div>
            </div>
        @endunless

        <div class="afs-g-search">
            <x-filament::icon icon="heroicon-o-magnifying-glass" />
            <label for="afs-guide-search" class="sr-only">Search all guides</label>
            <input id="afs-guide-search" type="search" x-model="q" placeholder="Search every guide — e.g. &quot;redirect_uri_mismatch&quot;, &quot;App Password&quot;, &quot;webhook&quot;, &quot;CASA&quot;" autocomplete="off">
        </div>

        <div class="afs-g-results" x-show="q.trim().length >= 2" x-cloak>
            <p class="afs-g-small" x-text="results.length ? results.length + ' guide' + (results.length === 1 ? '' : 's') + ' mention this' : 'No guide mentions that yet — try another word.'"></p>
            <template x-for="r in results" :key="r.key">
                <a class="afs-g-result" :href="r.url">
                    <strong x-html="mark(r.title)"></strong>
                    <p x-html="mark(r.snippet)"></p>
                </a>
            </template>
        </div>

        <div x-show="q.trim().length < 2">
            @foreach ($phases as $phaseKey => $phase)
                <section class="afs-g-phase" aria-labelledby="phase-{{ $phaseKey }}">
                    <div class="afs-g-phase-head">
                        <h2 id="phase-{{ $phaseKey }}">{{ $phase['title'] }}</h2>
                        <p>{{ $phase['description'] }}</p>
                    </div>

                    <div class="afs-g-cards">
                        @foreach ($phase['guides'] as $guide)
                            @php($icon = $guide['brand'] ? ($brandIcons[$guide['brand']] ?? null) : null)
                            <article class="afs-g-card" data-guide="{{ $guide['key'] }}" data-status="{{ $guide['status']['state'] }}">
                                <div class="afs-g-card-top">
                                    <div class="afs-g-logo" @if ($icon) style="background: {{ $icon['hex'] }}14; color: {{ $icon['hex'] === '#000000' || $icon['hex'] === '#191919' ? 'currentColor' : $icon['hex'] }}" @endif>
                                        @if ($icon)
                                            <svg viewBox="0 0 24 24" role="img" aria-label="{{ $icon['title'] }}" fill="currentColor"><path d="{{ $icon['path'] }}" /></svg>
                                        @else
                                            <x-filament::icon :icon="$guide['icon']" />
                                        @endif
                                    </div>
                                    <div style="min-width:0">
                                        <span class="afs-g-num">STEP {{ $guide['number'] }}</span>
                                        <h3><a href="{{ $guide['url'] }}">{{ $guide['title'] }}</a></h3>
                                    </div>
                                </div>

                                <div class="afs-g-chips">
                                    <span class="afs-g-pill afs-g-pill--plain afs-g-pill--{{ $guide['priority'] }}">{{ ucfirst($guide['priority']) }}</span>
                                    <span class="afs-g-pill afs-g-pill--{{ $guide['status']['state'] }}" title="{{ $guide['status']['detail'] }}">{{ $guide['status']['label'] }}</span>
                                </div>

                                <p>{{ $guide['enables'] }}</p>

                                <dl class="afs-g-facts">
                                    <div><x-filament::icon icon="heroicon-o-currency-dollar" /><span><strong>Cost:</strong> {{ $guide['cost'] }} <a href="{{ $guide['cost_source'] }}" target="_blank" rel="noopener noreferrer" style="position:relative;z-index:1">source</a></span></div>
                                    <div><x-filament::icon icon="heroicon-o-clock" /><span><strong>Time:</strong> {{ $guide['time'] }}</span></div>
                                    @if ($guide['status']['detail'])
                                        <div><x-filament::icon icon="heroicon-o-signal" /><span>{{ $guide['status']['detail'] }}</span></div>
                                    @endif
                                </dl>

                                <div class="afs-g-card-foot">
                                    <a href="{{ $guide['url'] }}" class="afs-g-btn afs-g-btn--primary"><x-filament::icon icon="heroicon-o-book-open" /> Open guide</a>
                                    @if ($guide['target']['url'])
                                        <a href="{{ $guide['target']['url'] }}" class="afs-g-btn"><x-filament::icon icon="heroicon-o-cog-6-tooth" /> {{ $guide['target']['user_side'] ? 'Customer page' : 'Settings page' }}</a>
                                    @elseif ($guide['target']['env'])
                                        <span class="afs-g-btn" title="{{ implode(', ', $guide['target']['env']) }}"><x-filament::icon icon="heroicon-o-document-text" /> .env file</span>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                </section>
            @endforeach
        </div>

        <p class="afs-g-small">Statuses come from the same checks as Connections Health, read when this page loads. "Set up — not verified" means keys are saved but nobody has pressed the page's Verify/Test button since. Guide wording and costs: config/integration_guides.php.</p>
    </div>
</x-filament-panels::page>
