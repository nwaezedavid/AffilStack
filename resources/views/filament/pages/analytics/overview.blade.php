<x-filament-panels::page>
    @include('filament.pages._panel-styles')

    <div class="afs-panel-hero afs-panel-hero--split">
        <p>
            Traffic, where it comes from, what's popular, and who's paying — everything in one place. 404s live in
            their own monitor (linked below); this page is the summary.
        </p>
        <div class="afs-panel-periods">
            @foreach ($this->periods() as $period)
                <button
                    type="button"
                    wire:click="setPeriod({{ $period }})"
                    class="afs-panel-period-btn {{ $days === $period ? 'is-active' : '' }}"
                >
                    {{ $period === 365 ? '1y' : "{$period}d" }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="afs-panel-stat-grid">
        @foreach ($this->stats() as $stat)
            <div class="afs-panel-card">
                <div class="afs-panel-stat-value">{{ $stat['value'] }}</div>
                <div class="afs-panel-stat-label">{{ $stat['label'] }}</div>
                <div class="afs-panel-stat-hint">{{ $stat['hint'] }}</div>
            </div>
        @endforeach
    </div>

    <h2 class="afs-panel-heading">Traffic over time</h2>
    <div class="afs-panel-card">
        @php($series = $this->dailySeries())
        @php($max = max(1, collect($series)->max('views')))
        <div class="afs-panel-bars">
            @foreach ($series as $point)
                <div class="afs-panel-bar" title="{{ $point['label'] }}: {{ $point['views'] }} views">
                    <div class="afs-panel-bar-fill" style="height: {{ max(2, round(($point['views'] / $max) * 100)) }}%"></div>
                </div>
            @endforeach
        </div>
        @if (count($series) <= 31)
            <div class="afs-panel-bar-labels">
                @foreach ($series as $point)
                    <span>{{ $point['label'] }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <div class="afs-panel-columns-2">
        <div>
            <h2 class="afs-panel-heading">Popular pages</h2>
            <div class="afs-panel-card">
                @php($pages = $this->popularPages())
                @if (empty($pages))
                    <p class="afs-panel-empty">No recorded visits yet for this period.</p>
                @else
                    <div style="overflow-x: auto;">
                    <table class="afs-panel-table">
                        <thead>
                            <tr>
                                <th>Page</th>
                                <th class="afs-panel-num">Views</th>
                                <th class="afs-panel-num">Avg. time</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pages as $page)
                                <tr>
                                    <td>{{ $page['path'] }}</td>
                                    <td class="afs-panel-num">{{ number_format($page['views']) }}</td>
                                    <td class="afs-panel-num">{{ $page['avg_duration'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>
        </div>

        <div>
            <h2 class="afs-panel-heading">Traffic sources</h2>
            <div class="afs-panel-card">
                @php($sources = $this->trafficSources())
                @if (empty($sources))
                    <p class="afs-panel-empty">No recorded visits yet for this period.</p>
                @else
                    <div style="overflow-x: auto;">
                    <table class="afs-panel-table">
                        <thead>
                            <tr>
                                <th>Source</th>
                                <th class="afs-panel-num">Views</th>
                                <th class="afs-panel-num">Share</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sources as $source)
                                <tr>
                                    <td>
                                        {{ $source['source'] }}
                                        <span class="afs-panel-bar-track">
                                            <span class="afs-panel-bar-track-fill" style="width: {{ $source['percent'] }}%"></span>
                                        </span>
                                    </td>
                                    <td class="afs-panel-num">{{ number_format($source['views']) }}</td>
                                    <td class="afs-panel-num">{{ $source['percent'] }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="afs-panel-heading-row">
        <h2 class="afs-panel-heading">Paid users by plan</h2>
    </div>
    <div class="afs-panel-card">
        @php($plans = $this->paidUsersByPlan())
        @if (empty($plans))
            <p class="afs-panel-empty">No active or trialing subscriptions yet.</p>
        @else
            <div style="overflow-x: auto;">
            <table class="afs-panel-table">
                <thead>
                    <tr>
                        <th>Plan</th>
                        <th class="afs-panel-num">Subscribers</th>
                        <th class="afs-panel-num">MRR contribution</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($plans as $plan)
                        <tr>
                            <td>{{ $plan['plan'] }}</td>
                            <td class="afs-panel-num">{{ number_format($plan['count']) }}</td>
                            <td class="afs-panel-num">{{ $plan['mrr'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            </div>
        @endif
    </div>

    <div class="afs-panel-heading-row">
        <h2 class="afs-panel-heading">404s &amp; redirects</h2>
        <a href="{{ $this->notFoundMonitorUrl() }}" class="afs-panel-heading-link">Open 404 Monitor &rarr;</a>
    </div>
    <div class="afs-panel-card">
        <p style="margin: 0; font-size: 0.8125rem; color: #6b7280;">
            Every dead link on the public site is logged there, deduped by path. Open any one for its full detail —
            hits, first/last seen, and any redirect already in place — and turn it into a redirect from that page.
        </p>
    </div>
</x-filament-panels::page>
