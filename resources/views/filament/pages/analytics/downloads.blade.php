<x-filament-panels::page>
    @include('filament.pages.analytics._insights-styles')

    <div class="afs-ins">
        <div class="afs-ins-hero">
            <div class="afs-ins-hero-row">
                <div>
                    <div class="afs-ins-eyebrow">Downloads</div>
                    <h2>Take your data anywhere</h2>
                    <p>CSV files open in Excel, Google Sheets or Numbers. Pick a date range, then download. Every download is recorded in the audit log below.</p>
                </div>
                <div class="afs-ins-filter" role="group" aria-label="Date range">
                    @foreach ($this->presets() as $key => $label)
                        <button type="button" wire:click="applyPreset('{{ $key }}')" class="afs-ins-chip {{ $preset === $key ? 'is-active' : '' }}" aria-pressed="{{ $preset === $key ? 'true' : 'false' }}">{{ $label }}</button>
                    @endforeach
                    <div class="afs-ins-dates">
                        <label for="dl-from" class="sr-only">From</label>
                        <input id="dl-from" type="date" wire:model.live="from" max="{{ now()->toDateString() }}">
                        <span aria-hidden="true">→</span>
                        <label for="dl-to" class="sr-only">To</label>
                        <input id="dl-to" type="date" wire:model.live="to" max="{{ now()->toDateString() }}">
                    </div>
                </div>
            </div>
        </div>

        @unless ($this->canDownload())
            <div class="afs-ins-note afs-ins-note--warn">
                <x-filament::icon icon="heroicon-o-lock-closed" />
                <span>These files contain personal data, so only full administrators can download them. Ask an admin if you need one.</span>
            </div>
        @endunless

        <div class="afs-ins-grid afs-ins-grid--2">
            @foreach ($this->types() as $type => $meta)
                <div class="afs-ins-card" style="display:flex; flex-direction:column; gap:.75rem;">
                    <div>
                        <h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-table-cells" /> {{ $meta['label'] }}</h3>
                        <p class="afs-ins-card-sub" style="font-size:.8125rem; color: var(--ins-muted); margin-top:.35rem;">{{ $meta['description'] }}</p>
                    </div>
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:.75rem; margin-top:auto;">
                        <span class="afs-ins-pill">{{ $meta['dated'] ? $this->rangeLabel() : 'Current snapshot' }}</span>
                        @if ($this->canDownload())
                            <a href="{{ $this->exportUrl($type) }}" class="afs-ins-btn afs-ins-btn--primary"><x-filament::icon icon="heroicon-o-arrow-down-tray" /> Download CSV</a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="afs-ins-card">
            <div class="afs-ins-card-head">
                <div>
                    <h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-user-group" /> Ad audience files</h3>
                    <p class="afs-ins-card-sub">Hashed emails/phones of people who allowed advertising cookies — ready for Meta Custom Audiences and Google Customer Match.</p>
                </div>
                <a href="{{ \App\Filament\Resources\AnalyticsSegments\AnalyticsSegmentResource::getUrl() }}" class="afs-ins-btn">Manage audiences</a>
            </div>
            <div class="afs-ins-table-wrap">
                <table class="afs-ins-table">
                    <thead><tr><th>Audience</th><th class="afs-ins-num">Can upload</th><th class="afs-ins-num">Download</th></tr></thead>
                    <tbody>
                        @foreach ($this->segments() as $segment)
                            <tr>
                                <td><strong>{{ $segment->name }}</strong>@if ($segment->purpose === 'exclude') <span class="afs-ins-pill afs-ins-pill--warn">exclusion</span>@endif</td>
                                <td class="afs-ins-num">{{ number_format(app(\App\Services\Analytics\SegmentEvaluator::class)->exportableCount($segment)) }}</td>
                                <td class="afs-ins-num">
                                    @if ($this->canDownload())
                                        <a class="afs-ins-btn" href="{{ route('admin.analytics.export', ['type' => 'audience', 'segment' => $segment->id, 'platform' => 'meta']) }}">Meta</a>
                                        <a class="afs-ins-btn" href="{{ route('admin.analytics.export', ['type' => 'audience', 'segment' => $segment->id, 'platform' => 'google']) }}">Google Ads</a>
                                    @else
                                        —
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="afs-ins-card">
            <div class="afs-ins-card-head"><div><h3 class="afs-ins-card-title"><x-filament::icon icon="heroicon-o-finger-print" /> Download audit log</h3><p class="afs-ins-card-sub">Last 10 downloads (kept in the activity log)</p></div></div>
            @php($downloads = $this->recentDownloads())
            @if ($downloads->isEmpty())
                <div class="afs-ins-empty"><x-filament::icon icon="heroicon-o-document-arrow-down" /><span>No downloads yet.</span></div>
            @else
                <div class="afs-ins-table-wrap">
                    <table class="afs-ins-table">
                        <thead><tr><th>When</th><th>Who</th><th>What</th></tr></thead>
                        <tbody>
                            @foreach ($downloads as $entry)
                                <tr>
                                    <td>{{ $entry->created_at?->format('M j, Y H:i') }}</td>
                                    <td>{{ $entry->causer?->email ?? '—' }}</td>
                                    <td>{{ $entry->description }}@if ($entry->getProperty('segment')) — {{ $entry->getProperty('segment') }} ({{ $entry->getProperty('platform') }})@elseif ($entry->getProperty('from')) — {{ $entry->getProperty('from') }} to {{ $entry->getProperty('to') }}@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
