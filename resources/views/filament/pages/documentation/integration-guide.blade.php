<x-filament-panels::page>
    @include('filament.pages.documentation.guides._styles')

    @php
        $registry = $this->registry();
        $meta = $this->meta();
        $status = $registry->status($guide);
        $target = $registry->adminTarget($guide);
        $body = \App\Filament\Pages\Documentation\IntegrationGuide::renderBody($guide);
        $toc = \App\Filament\Pages\Documentation\IntegrationGuide::tableOfContents($body);
        $neighbours = $this->neighbours();
        $icon = $meta['brand'] ? (config('brand_icons')[$meta['brand']] ?? null) : null;
    @endphp

    <div class="afs-g">
        <a href="{{ \App\Filament\Pages\Documentation\IntegrationGuides::getUrl() }}" class="afs-g-back"><x-filament::icon icon="heroicon-o-arrow-left" /> All integration guides</a>

        <div class="afs-g-hero">
            <div class="afs-g-hero-row">
                <div>
                    <span class="afs-g-eyebrow">
                        @if ($icon)
                            <svg viewBox="0 0 24 24" fill="currentColor" style="width:0.9rem;height:0.9rem" aria-hidden="true"><path d="{{ $icon['path'] }}" /></svg>
                        @else
                            <x-filament::icon :icon="$meta['icon']" style="width:0.9rem;height:0.9rem" />
                        @endif
                        Setup guide · last reviewed {{ $registry->lastReviewed() }}
                    </span>
                    <h1>{{ $meta['title'] }}</h1>
                    <p>{{ $meta['enables'] }}</p>
                </div>
                @if ($target['url'])
                    <a href="{{ $target['url'] }}" class="afs-g-btn afs-g-btn--light"><x-filament::icon icon="heroicon-o-cog-6-tooth" /> {{ $target['user_side'] ? 'Open the customer page' : 'Open the settings page' }}</a>
                @endif
            </div>
        </div>

        <div class="afs-g-meta">
            <div class="afs-g-meta-item">
                <span><x-filament::icon icon="heroicon-o-signal" /> Live status</span>
                <span class="afs-g-pill afs-g-pill--{{ $status['state'] }}">{{ $status['label'] }}</span>
                @if ($status['detail'])
                    <div class="afs-g-small" style="margin-top:0.35rem">{{ $status['detail'] }}</div>
                @endif
            </div>
            <div class="afs-g-meta-item">
                <span><x-filament::icon icon="heroicon-o-flag" /> Priority</span>
                <span class="afs-g-pill afs-g-pill--plain afs-g-pill--{{ $meta['priority'] }}">{{ ucfirst($meta['priority']) }}</span>
            </div>
            <div class="afs-g-meta-item">
                <span><x-filament::icon icon="heroicon-o-currency-dollar" /> Cost</span>
                {{ $meta['cost'] }} <a href="{{ $meta['cost_source'] }}" target="_blank" rel="noopener noreferrer">source</a>
            </div>
            <div class="afs-g-meta-item">
                <span><x-filament::icon icon="heroicon-o-clock" /> Setup time</span>
                {{ $meta['time'] }}
            </div>
            <div class="afs-g-meta-item">
                <span><x-filament::icon icon="heroicon-o-map-pin" /> Keys go in</span>
                @if ($target['url'])
                    <a href="{{ $target['url'] }}">{{ $target['label'] }}</a>
                @elseif ($target['label'])
                    {{ $target['label'] }}
                @endif
                @if ($target['env'])
                    <div class="afs-g-small" style="margin-top:0.2rem">Server <code>.env</code>: {{ implode(', ', $target['env']) }}</div>
                @endif
            </div>
        </div>

        @if (! empty($meta['volatile']))
            <div class="afs-g-callout afs-g-callout--info" role="note">
                <x-filament::icon icon="heroicon-o-arrow-path" />
                <div><span class="afs-g-callout-title">Changes often — double-check on the provider's site</span>{{ $meta['volatile'] }}</div>
            </div>
        @endif

        @unless ($registry->siteUrlLooksLive())
            <div class="afs-g-callout afs-g-callout--warning" role="note">
                <x-filament::icon icon="heroicon-o-exclamation-triangle" />
                <div><span class="afs-g-callout-title">You're viewing a non-live copy ({{ url('/') }})</span>The addresses below are built from this copy's address. Open this guide on the live site to copy the real ones.</div>
            </div>
        @endunless

        <div class="afs-g-layout">
            <div>
                {!! $body !!}

                <section class="afs-g-section" id="official-docs">
                    <h2><x-filament::icon icon="heroicon-o-link" /> Official documentation</h2>
                    <ul class="afs-g-docs">
                        @foreach ($meta['docs'] ?? [] as [$label, $href])
                            <li><a href="{{ $href }}" target="_blank" rel="noopener noreferrer"><x-filament::icon icon="heroicon-o-arrow-top-right-on-square" /> {{ $label }}</a></li>
                        @endforeach
                    </ul>
                    <p class="afs-g-small" style="margin-top:0.75rem">Last reviewed {{ $registry->lastReviewed() }} against the providers' own documentation. Consoles get redesigned — if a button has moved, the provider's guide above is the source of truth.</p>
                </section>

                <nav class="afs-g-pager" aria-label="Other guides">
                    @if ($neighbours['previous'])
                        <a class="afs-g-btn" href="{{ $neighbours['previous']['url'] }}"><x-filament::icon icon="heroicon-o-arrow-left" /> {{ $neighbours['previous']['title'] }}</a>
                    @else
                        <span></span>
                    @endif
                    @if ($neighbours['next'])
                        <a class="afs-g-btn afs-g-btn--primary" href="{{ $neighbours['next']['url'] }}">Next: {{ $neighbours['next']['title'] }} <x-filament::icon icon="heroicon-o-arrow-right" /></a>
                    @endif
                </nav>
            </div>

            <nav class="afs-g-toc" aria-label="On this page">
                <strong>On this page</strong>
                <ol>
                    @foreach ($toc as $entry)
                        <li><a href="#{{ $entry['id'] }}">{{ $entry['title'] }}</a></li>
                    @endforeach
                    <li><a href="#official-docs">Official documentation</a></li>
                </ol>
            </nav>
        </div>
    </div>
</x-filament-panels::page>
