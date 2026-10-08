<x-filament-panels::page>
    @include('filament.pages.analytics._insights-styles')

    @php
        $insights = $this->insights;
        $kpis = $insights->kpis();
        $funnel = $insights->funnel();
        $consent = $insights->consent();
        $disclosure = $this->disclosure();
        $fmt = fn ($n) => $n === null ? '—' : number_format($n);
    @endphp

    <div class="afs-ins">
        <div class="afs-ins-hero">
            <div class="afs-ins-hero-row">
                <div>
                    <div class="afs-ins-eyebrow">First-party, consent-based analytics</div>
                    <h2>What your visitors and customers do on AffilStack</h2>
                    <p>Traffic, where it comes from, what people look at, the path from first visit to paying customer, and which dashboard features customers rely on — {{ $this->rangeLabel() }}.</p>
                </div>
                <div class="afs-ins-filter" role="group" aria-label="Date range">
                    @foreach ($this->presets() as $key => $label)
                        <button type="button" wire:click="applyPreset('{{ $key }}')" class="afs-ins-chip {{ $preset === $key ? 'is-active' : '' }}" aria-pressed="{{ $preset === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                    <div class="afs-ins-dates">
                        <label for="ins-from" class="sr-only">From</label>
                        <input id="ins-from" type="date" wire:model.live="from" max="{{ now()->toDateString() }}">
                        <span aria-hidden="true">→</span>
                        <label for="ins-to" class="sr-only">To</label>
                        <input id="ins-to" type="date" wire:model.live="to" max="{{ now()->toDateString() }}">
                    </div>
                </div>
            </div>
        </div>

        <details class="afs-ins-note" style="display:block;">
            <summary style="display:flex; gap:.75rem; align-items:flex-start; cursor:pointer; list-style:none;">
                <x-filament::icon icon="heroicon-o-information-circle" />
                <span><strong>What this data is — and isn't.</strong> A website can only see what happens on its own pages, so this measures behaviour on AffilStack (the public site and the dashboard), never how people use the rest of the internet. Visit-level data exists only for people who clicked "Accept" for analytics; everyone else is an anonymous page count. Data is kept for {{ $disclosure['retention'] }} months. <u>Show details</u></span>
            </summary>
            <div class="afs-ins-grid afs-ins-grid--2" style="margin-top: 1rem;">
                <div>
                    <p style="font-weight:650; margin:0 0 .4rem;">Collected (with the visitor's consent)</p>
                    <ul class="afs-ins-list">
                        @foreach ($disclosure['collected'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
                <div>
                    <p style="font-weight:650; margin:0 0 .4rem;">Never collected</p>
                    <ul class="afs-ins-list">
                        @foreach ($disclosure['not_collected'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                    <p style="margin:.75rem 0 0; color: var(--ins-muted);">Consent policy version <span class="afs-ins-mono">{{ $disclosure['version'] }}</span> · change banner text, version and retention in <a href="{{ \App\Filament\Pages\Analytics\CookieConsentSettings::getUrl() }}" style="color: var(--ins-brand); text-decoration: underline;">Cookie Consent</a>.</p>
                </div>
            </div>
        </details>

        <div class="afs-ins-kpis afs-ins-kpis--6">
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-users" /> Visitors</div>
                <div class="afs-ins-kpi-value">{{ $fmt($kpis['visitors']) }}</div>
                <div class="afs-ins-kpi-hint">{{ $fmt($kpis['new_visitors']) }} new · consented only</div>
            </div>
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-arrow-path-rounded-square" /> Visits</div>
                <div class="afs-ins-kpi-value">{{ $fmt($kpis['sessions']) }}</div>
                <div class="afs-ins-kpi-hint">{{ $kpis['pages_per_session'] ?? '—' }} pages per visit</div>
            </div>
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-document-text" /> Page views</div>
                <div class="afs-ins-kpi-value">{{ $fmt($kpis['page_views']) }}</div>
                <div class="afs-ins-kpi-hint">{{ $kpis['anonymous_share'] !== null ? $kpis['anonymous_share'].'% anonymous' : 'incl. anonymous counts' }}</div>
            </div>
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-clock" /> Engaged time</div>
                <div class="afs-ins-kpi-value">{{ \App\Filament\Pages\Analytics\AudienceInsights::formatSeconds($kpis['avg_engaged_seconds']) }}</div>
                <div class="afs-ins-kpi-hint">average per visit (tab visible)</div>
            </div>
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-arrow-uturn-left" /> Bounce rate</div>
                <div class="afs-ins-kpi-value">{{ $kpis['bounce_rate'] !== null ? $kpis['bounce_rate'].'%' : '—' }}</div>
                <div class="afs-ins-kpi-hint">left after one page, under 10s</div>
            </div>
            <div class="afs-ins-kpi">
                <div class="afs-ins-kpi-label"><x-filament::icon icon="heroicon-o-check-badge" /> Paid signups</div>
                <div class="afs-ins-kpi-value">{{ $fmt($kpis['signups']) }}</div>
                <div class="afs-ins-kpi-hint">{{ $fmt($kpis['revenue_events']) }} payments in total</div>
            </div>
        </div>

        <div class="afs-ins-card">
            <div class="afs-ins-card-head">
                <div>
                    <h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-chart-bar-square" /> Traffic over time</h3>
                    <p class="afs-ins-card-sub">Daily, {{ $this->rangeLabel() }}</p>
                </div>
            </div>
            @include('filament.pages.analytics.partials.line-chart', ['series' => $insights->trafficSeries()])
            <p class="afs-ins-explain"><strong>What this means:</strong> the blue area is every page view (including anonymous counts from people who declined analytics); gold is visits and the dotted teal line is individual visitors who allowed analytics. Spikes usually line up with a post, an email or an ad — compare with "Where visitors come from" below.</p>
        </div>

        <div class="afs-ins-grid afs-ins-grid--2">
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-funnel" /> From first visit to paying customer</h3></div>
                <div class="afs-ins-funnel">
                    @php($funnelMax = max(1, $funnel['steps'][0]['value']))
                    @foreach ($funnel['steps'] as $i => $step)
                        <div class="afs-ins-funnel-step">
                            <span>{{ $step['label'] }}</span>
                            <div class="afs-ins-funnel-bar" aria-hidden="true">
                                <div class="afs-ins-funnel-fill" style="width: {{ max(6, round($step['value'] / $funnelMax * 100)) }}%; {{ $i === 3 ? 'background: linear-gradient(90deg,#047857,#10b981);' : '' }}">{{ number_format($step['value']) }}</div>
                            </div>
                            <span class="afs-ins-funnel-rate">
                                @if ($step['rate_from_previous'] !== null)
                                    <b>{{ $step['rate_from_previous'] }}%</b> of previous
                                @else
                                    <span class="sr-only">{{ number_format($step['value']) }} people</span>starting point
                                @endif
                            </span>
                        </div>
                    @endforeach
                </div>
                @if (array_sum($funnel['anonymous']) > 0)
                    <p class="afs-ins-card-sub" style="margin-top:.75rem;">Plus {{ $funnel['anonymous']['checkout_started'] }} checkout(s) and {{ $funnel['anonymous']['signup_completed'] }} signup(s) from people who declined analytics (counted, not tracked).</p>
                @endif
                <p class="afs-ins-explain"><strong>What this means:</strong> each bar counts distinct people. The biggest drop shows where to focus — a big fall from "Viewed pricing" to "Started checkout" points at pricing or plan clarity; from checkout to paid points at payment friction. People who dropped off are ready-made ad audiences in <a href="{{ \App\Filament\Resources\AnalyticsSegments\AnalyticsSegmentResource::getUrl() }}" style="color: var(--ins-brand); text-decoration: underline;">Audiences</a>.</p>
            </div>

            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-globe-alt" /> Where visitors come from</h3></div>
                @include('filament.pages.analytics.partials.donut', ['rows' => $insights->channels(), 'title' => 'visits'])
                <p class="afs-ins-explain"><strong>What this means:</strong> the channel of each visit. <em>Organic Search</em> is Google/Bing, <em>Social</em> is people clicking from social networks, <em>Paid</em> is ad clicks (UTM medium "cpc" or an ad click id), <em>Direct</em> is typed URLs and bookmarks. Tag your links with UTM parameters so campaigns show up by name below.</p>
            </div>
        </div>

        <div class="afs-ins-card">
            <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-megaphone" /> Sources, mediums &amp; campaigns</h3><p class="afs-ins-card-sub">Top 10 by visits</p></div></div>
            @php($sources = $insights->sourceMediums())
            @if (empty($sources))
                <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-megaphone" /><span>No visits recorded for this period yet.</span></div>
            @else
                <div class="afs-ins-table-wrap">
                    <table class="afs-ins-table">
                        <thead><tr><th>Source</th><th>Medium</th><th>Campaign</th><th class="afs-ins-num">Visits</th></tr></thead>
                        <tbody>
                            @foreach ($sources as $row)
                                <tr>
                                    <td>{{ $row['source'] }}</td>
                                    <td><span class="afs-ins-pill">{{ $row['medium'] }}</span></td>
                                    <td>{{ $row['campaign'] ?? '—' }}</td>
                                    <td class="afs-ins-num">{{ number_format($row['sessions']) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            <p class="afs-ins-explain"><strong>What this means:</strong> "source" is the site or platform (google, facebook, newsletter), "medium" the type of traffic (organic, cpc, email, referral) and "campaign" your utm_campaign tag. A campaign with many visits but few signups in the funnel above needs a better landing page or offer.</p>
        </div>

        <div class="afs-ins-grid afs-ins-grid--2">
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-arrow-down-on-square" /> Top landing pages</h3></div>
                @php($landing = $insights->landingPages())
                @if (empty($landing))
                    <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-document" /><span>No visits recorded for this period yet.</span></div>
                @else
                    <div class="afs-ins-table-wrap">
                        <table class="afs-ins-table">
                            <thead><tr><th>Page</th><th class="afs-ins-num">Visits</th><th class="afs-ins-num">Bounce</th><th class="afs-ins-num">Pages/visit</th></tr></thead>
                            <tbody>
                                @foreach ($landing as $row)
                                    <tr>
                                        <td class="afs-ins-mono">{{ $row['path'] }}</td>
                                        <td class="afs-ins-num">{{ number_format($row['sessions']) }}</td>
                                        <td class="afs-ins-num">{{ $row['bounce_rate'] }}%</td>
                                        <td class="afs-ins-num">{{ $row['pages'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="afs-ins-explain"><strong>What this means:</strong> the first page of each visit — your real "front doors". A high bounce rate means people arrive and leave without doing anything; improve that page's headline and call-to-action first.</p>
            </div>

            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-document-text" /> Most viewed pages</h3></div>
                @php($pages = $insights->topPages())
                @if (empty($pages))
                    <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-document" /><span>No page views recorded for this period yet.</span></div>
                @else
                    <div class="afs-ins-table-wrap">
                        <table class="afs-ins-table">
                            <thead><tr><th>Page</th><th class="afs-ins-num">Views</th><th class="afs-ins-num">Avg. time</th><th class="afs-ins-num">Scroll</th></tr></thead>
                            <tbody>
                                @foreach ($pages as $row)
                                    <tr>
                                        <td class="afs-ins-mono">{{ $row['path'] }}</td>
                                        <td class="afs-ins-num">{{ number_format($row['views']) }}</td>
                                        <td class="afs-ins-num">{{ \App\Filament\Pages\Analytics\AudienceInsights::formatSeconds($row['avg_seconds']) }}</td>
                                        <td class="afs-ins-num">{{ $row['avg_scroll'] !== null ? $row['avg_scroll'].'%' : '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
                <p class="afs-ins-explain"><strong>What this means:</strong> what people read most, how long they stay and how far down they scroll on average. A popular page with low scroll depth loses readers before its key message — move it higher.</p>
            </div>
        </div>

        <div class="afs-ins-grid afs-ins-grid--3">
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-device-phone-mobile" /> Devices</h3></div>
                @include('filament.pages.analytics.partials.donut', ['rows' => $insights->breakdown('device_type'), 'title' => 'visits'])
                <p class="afs-ins-explain"><strong>What this means:</strong> if most visits are on mobile, review every page and ad creative on a phone first.</p>
            </div>
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-window" /> Browsers</h3></div>
                @include('filament.pages.analytics.partials.bar-list', ['rows' => $insights->breakdown('browser'), 'color' => '#7c3aed'])
                <p class="afs-ins-explain"><strong>What this means:</strong> "In-app (Meta)" means people opened your link inside Facebook or Instagram — logins and payments there can be fiddly, so keep those flows simple.</p>
            </div>
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-map" /> Countries</h3></div>
                @include('filament.pages.analytics.partials.bar-list', ['rows' => collect($insights->breakdown('country'))->map(fn ($r) => ['label' => ($r['label'] !== 'Unknown' ? (\App\Support\Countries::name($r['label']) ?? $r['label']) : 'Unknown')] + $r)->all(), 'color' => '#0d9488'])
                <p class="afs-ins-explain"><strong>What this means:</strong> approximate location from the visitor's IP address (looked up, then discarded). Use it to pick ad targeting and the currency to lead with.</p>
            </div>
        </div>

        <div class="afs-ins-grid afs-ins-grid--2">
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-sparkles" /> Top dashboard features</h3><p class="afs-ins-card-sub">How often customers used each feature</p></div></div>
                @include('filament.pages.analytics.partials.bar-list', ['rows' => $insights->topFeatures(), 'color' => '#7c3aed', 'empty' => 'No feature use recorded in this period yet.'])
                <p class="afs-ins-explain"><strong>What this means:</strong> what paying customers actually use. Features near the top are your product's real value — feature them in ads and onboarding. Features near the bottom may need better discovery or a tutorial.</p>
            </div>
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-cursor-arrow-rays" /> Actions people take</h3><p class="afs-ins-card-sub">All tracked events in this period</p></div></div>
                @include('filament.pages.analytics.partials.bar-list', ['rows' => $insights->eventTotals(), 'color' => '#2452d9', 'empty' => 'No events recorded in this period yet.'])
                <p class="afs-ins-explain"><strong>What this means:</strong> clicks on calls-to-action, pricing and plan views, checkouts, logins and custom events from <span class="afs-ins-mono">affilstackTrack()</span>. Add <span class="afs-ins-mono">data-track-cta="Label"</span> to any button to count it here.</p>
            </div>
        </div>

        <div class="afs-ins-card">
            <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-light-bulb" /> What customers are interested in</h3><p class="afs-ins-card-sub">From the profiles of signed-in users who allowed analytics</p></div></div>
            @php($interests = $insights->interests())
            <div class="afs-ins-grid afs-ins-grid--3">
                <div>
                    <p style="font-size:.8125rem; font-weight:650; margin:0 0 .6rem;">Affiliate networks they pick</p>
                    @include('filament.pages.analytics.partials.bar-list', ['rows' => $interests['networks'], 'color' => '#c9a24a', 'unit' => 'people', 'empty' => 'No research yet.'])
                </div>
                <div>
                    <p style="font-size:.8125rem; font-weight:650; margin:0 0 .6rem;">Content they generate</p>
                    @include('filament.pages.analytics.partials.bar-list', ['rows' => $interests['modules'], 'color' => '#7c3aed', 'empty' => 'No content generated yet.'])
                </div>
                <div>
                    <p style="font-size:.8125rem; font-weight:650; margin:0 0 .6rem;">Products they research</p>
                    @include('filament.pages.analytics.partials.bar-list', ['rows' => $interests['products'], 'color' => '#0d9488', 'unit' => 'people', 'empty' => 'No products researched yet.'])
                </div>
            </div>
            <p class="afs-ins-explain"><strong>What this means:</strong> what your customers are trying to sell and how. Use it to choose tutorial and blog topics, partner with the networks they use, and write ads in their language ("promote your ClickBank offers…").</p>
        </div>

        <div class="afs-ins-grid afs-ins-grid--2">
            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-user-group" /> Lifecycle stages</h3><p class="afs-ins-card-sub">Everyone with a profile, right now</p></div></div>
                <div class="afs-ins-stages">
                    @foreach ($insights->lifecycle() as $stage)
                        <div class="afs-ins-stage">
                            <div class="afs-ins-stage-value">{{ number_format($stage['value']) }}</div>
                            <div class="afs-ins-stage-label">{{ $stage['label'] }}</div>
                        </div>
                    @endforeach
                </div>
                <p class="afs-ins-explain"><strong>What this means:</strong> visitors move from <em>Visitor</em> → <em>Engaged</em> (came back or explored) → <em>Viewed pricing</em> → <em>Started checkout</em> → <em>New customer</em>, then <em>Active</em> (used the dashboard in the last 14 days), <em>At risk</em> (paying but quiet) or <em>Churned</em> (subscription ended). Refreshed every 30 minutes.</p>
            </div>

            <div class="afs-ins-card">
                <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-shield-check" /> Cookie consent choices</h3><p class="afs-ins-card-sub">Latest choice per browser in this period</p></div>
                    <a href="{{ \App\Filament\Resources\ConsentRecords\ConsentRecordResource::getUrl() }}" class="afs-ins-btn">Consent log</a>
                </div>
                <div class="afs-ins-kpis" style="grid-template-columns: repeat(3, minmax(0,1fr)); margin-bottom: 1rem;">
                    <div class="afs-ins-stage"><div class="afs-ins-stage-value">{{ number_format($consent['total']) }}</div><div class="afs-ins-stage-label">choices made</div></div>
                    <div class="afs-ins-stage"><div class="afs-ins-stage-value">{{ $consent['analytics_rate'] !== null ? $consent['analytics_rate'].'%' : '—' }}</div><div class="afs-ins-stage-label">allow analytics</div></div>
                    <div class="afs-ins-stage"><div class="afs-ins-stage-value">{{ $consent['marketing_rate'] !== null ? $consent['marketing_rate'].'%' : '—' }}</div><div class="afs-ins-stage-label">allow advertising</div></div>
                </div>
                @include('filament.pages.analytics.partials.bar-list', ['rows' => $consent['actions'], 'color' => '#c9a24a', 'empty' => 'No cookie choices recorded in this period yet.'])
                <p class="afs-ins-explain"><strong>What this means:</strong> how many visitors let you measure them. Everything above about visits and interests only covers the "allow analytics" share; ad audiences only include people who allowed advertising. {{ $consent['signals'] }} browser(s) sent a privacy signal (GPC/Do Not Track) and were automatically opted out.</p>
            </div>
        </div>
    </div>
</x-filament-panels::page>
