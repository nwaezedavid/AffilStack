@extends('layouts.partner')

@section('title', 'Overview')

@php
    use App\Services\Referrals\PartnerStatsService;
    use App\Support\ReferralProgram;

    $firstName = \Illuminate\Support\Str::of($user->name)->trim()->before(' ')->value() ?: 'partner';
    $metrics = [
        'earnings' => ['label' => 'Earnings', 'icon' => 'banknotes', 'color' => '#059669', 'money' => true],
        'clicks' => ['label' => 'Link clicks', 'icon' => 'cursor-arrow-rays', 'color' => '#2452d9', 'money' => false],
        'signups' => ['label' => 'Sign-ups', 'icon' => 'user-plus', 'color' => '#7c3aed', 'money' => false],
        'paying' => ['label' => 'Paying customers', 'icon' => 'check-badge', 'color' => '#c9a24a', 'money' => false],
    ];
    $channelLabels = ['banner' => 'Banners & ads', 'email' => 'Email', 'social' => 'Social', 'text_link' => 'Text links', 'link' => 'Direct & campaign links'];
    $channelMax = max(1, ...array_values($report['channels'] ?: [0]));
    $hasActivity = array_sum($report['totals']) > 0;
@endphp

@section('content')
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm text-ink-600">Welcome back, {{ $firstName }}.</p>
            <p class="font-display text-2xl font-semibold text-navy-900">{{ $report['start']->format('M j, Y') }} – {{ $report['end']->format('M j, Y') }}</p>
        </div>
        <nav class="inline-flex max-w-full self-start overflow-x-auto rounded-full border border-line bg-surface p-1 text-xs font-medium sm:self-auto" aria-label="Date range">
            @foreach ($ranges as $key => $range)
                <a href="{{ route('partner.dashboard', ['range' => $key]) }}" @if ($report['range'] === $key) aria-current="true" @endif
                   class="whitespace-nowrap rounded-full px-3 py-1.5 transition {{ $report['range'] === $key ? 'bg-emerald-900 text-white' : 'text-ink-600 hover:text-navy-900' }}">{{ $range['label'] }}</a>
            @endforeach
        </nav>
    </div>

    {{-- Metric tiles double as the chart switcher. --}}
    <div class="grid grid-cols-2 gap-3 lg:grid-cols-4" role="tablist" aria-label="Choose a chart">
        @foreach ($metrics as $key => $metric)
            @php
                $value = $report['totals'][$key];
                $change = PartnerStatsService::change($value, $report['previous'][$key]);
            @endphp
            <button type="button" role="tab" data-metric="{{ $key }}" aria-selected="{{ $loop->first ? 'true' : 'false' }}" aria-controls="chart-{{ $key }}"
                    class="metric-tile text-left rounded-2xl border bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 aria-selected:border-emerald-600 aria-selected:ring-1 aria-selected:ring-emerald-600 border-line hover:border-emerald-200">
                <span class="flex items-center justify-between gap-2">
                    <span class="text-xs font-medium text-ink-600">{{ $metric['label'] }}</span>
                    <x-dynamic-component :component="'heroicon-o-'.$metric['icon']" class="h-4 w-4 text-ink-400" />
                </span>
                <span class="mt-2 block font-display text-2xl font-semibold text-navy-900 tabular-nums">{{ $metric['money'] ? ReferralProgram::money($value) : number_format($value) }}</span>
                <span class="mt-0.5 block text-xs tabular-nums {{ $change === null ? 'text-ink-400' : ($change >= 0 ? 'text-emerald-700' : 'text-rose-600') }}">
                    @if ($change === null)
                        No earlier period to compare
                    @else
                        {{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}% vs previous period
                    @endif
                </span>
            </button>
        @endforeach
    </div>

    <x-ui.card class="mt-4" padding="p-5 sm:p-6">
        @foreach ($metrics as $key => $metric)
            <div id="chart-{{ $key }}" role="tabpanel" data-chart="{{ $key }}" @unless ($loop->first) hidden @endunless>
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-base font-semibold text-navy-900">{{ $metric['label'] }} {{ $report['range'] === '12m' ? 'by month' : 'by day' }}</h2>
                    @if ($metric['money'])
                        <span class="hidden text-xs text-ink-400 sm:inline">In US dollars; naira earnings converted at today's rate</span>
                    @endif
                </div>
                <x-partner.chart :values="$report['series'][$key]" :labels="$report['labels']" :color="$metric['color']" :money="$metric['money']" :label="$metric['label']" />
            </div>
        @endforeach
        @unless ($hasActivity)
            <p class="mt-4 rounded-xl bg-emerald-50 px-4 py-3 text-sm text-emerald-900">Nothing in this period yet. Share your link or add a banner to start the chart moving. <a href="{{ route('partner.links') }}" class="font-semibold underline">Get your links</a></p>
        @endunless
    </x-ui.card>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-ui.card title="Conversion" description="How visitors turn into paying customers." icon="funnel">
            <ol class="space-y-4">
                @foreach ([
                    ['label' => 'Clicked your link', 'value' => $report['totals']['clicks'], 'rate' => null],
                    ['label' => 'Signed up', 'value' => $report['totals']['signups'], 'rate' => $report['rates']['signup']],
                    ['label' => 'Became paying customers', 'value' => $report['totals']['paying'], 'rate' => $report['rates']['paying']],
                ] as $step)
                    @php($width = $report['totals']['clicks'] > 0 ? max(4, round($step['value'] / max(1, $report['totals']['clicks']) * 100)) : 0)
                    <li>
                        <div class="flex items-baseline justify-between text-sm">
                            <span class="text-ink-900">{{ $step['label'] }}</span>
                            <span class="font-mono tabular-nums text-navy-900">{{ number_format($step['value']) }}</span>
                        </div>
                        <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-surface-muted">
                            <div class="h-full rounded-full bg-gradient-to-r from-emerald-600 to-teal-400" style="width: {{ $width }}%"></div>
                        </div>
                        @if ($step['rate'] !== null)
                            <p class="mt-1 text-xs text-ink-400">{{ $step['rate'] }}% of the step before</p>
                        @endif
                    </li>
                @endforeach
            </ol>
        </x-ui.card>

        <div class="rounded-2xl bg-gradient-to-br from-emerald-950 via-emerald-900 to-teal-700 p-5 sm:p-6 text-white shadow-sm">
            <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-gold-300">Your balance</p>
            <p class="mt-2 font-display text-4xl font-semibold tabular-nums">{{ ReferralProgram::money($balances['approved']) }}</p>
            <p class="text-sm text-emerald-100/80">approved and ready to request</p>
            <dl class="mt-5 grid grid-cols-2 gap-3 text-sm">
                <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/15">
                    <dt class="text-xs text-emerald-100/70">Pending review</dt>
                    <dd class="font-semibold tabular-nums">{{ ReferralProgram::money($balances['pending']) }}</dd>
                </div>
                <div class="rounded-xl bg-white/10 p-3 ring-1 ring-white/15">
                    <dt class="text-xs text-emerald-100/70">Paid to you</dt>
                    <dd class="font-semibold tabular-nums">{{ ReferralProgram::money($balances['paid']) }}</dd>
                </div>
            </dl>
            <a href="{{ route('partner.payouts') }}" class="mt-5 inline-flex items-center gap-1.5 rounded-full bg-gold-400 px-4 py-2 text-sm font-semibold text-emerald-950 hover:bg-gold-300">Earnings & payouts <x-heroicon-m-arrow-right class="h-4 w-4" /></a>
        </div>

        <x-ui.card title="Where clicks come from" description="This period, by channel." icon="chart-pie">
            @if ($report['channels'] === [])
                <p class="text-sm text-ink-600">No clicks in this period yet.</p>
            @else
                <ul class="space-y-3">
                    @foreach ($report['channels'] as $source => $count)
                        <li>
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-ink-900">{{ $channelLabels[$source] ?? ucfirst($source) }}</span>
                                <span class="font-mono tabular-nums text-ink-600">{{ number_format($count) }}</span>
                            </div>
                            <div class="mt-1 h-2 overflow-hidden rounded-full bg-surface-muted">
                                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-gold-400" style="width: {{ max(4, round($count / $channelMax * 100)) }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>
    </div>

    <x-ui.card class="mt-6" title="Campaigns" description="Results for each of your labelled links in this period." icon="tag" padding="p-0">
        <x-slot:headerActions>
            <x-ui.button size="sm" variant="secondary" icon="plus" :href="route('partner.links')">New campaign link</x-ui.button>
        </x-slot:headerActions>
        @if ($report['campaigns']->isEmpty())
            <p class="px-5 py-6 text-sm text-ink-600 sm:px-6">Create a campaign link for each place you promote AffilStack (a YouTube video, your newsletter, a WhatsApp group) and you'll see which one earns the most right here.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted font-mono text-xs uppercase tracking-wide text-ink-400">
                        <tr>
                            <th class="px-5 py-2.5 text-left sm:px-6">Campaign</th>
                            <th class="px-4 py-2.5 text-right">Clicks</th>
                            <th class="px-4 py-2.5 text-right">Sign-ups</th>
                            <th class="px-4 py-2.5 text-right">Paying</th>
                            <th class="px-5 py-2.5 text-right sm:px-6">Earnings</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($report['campaigns']->take(8) as $row)
                            <tr>
                                <td class="px-5 py-3 sm:px-6">
                                    <span class="font-medium text-navy-900">{{ $row['campaign']->name }}</span>
                                    <span class="block text-xs text-ink-400">{{ $row['campaign']->channelLabel() }}</span>
                                </td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($row['clicks']) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($row['signups']) }}</td>
                                <td class="px-4 py-3 text-right font-mono tabular-nums">{{ number_format($row['paying']) }}</td>
                                <td class="px-5 py-3 text-right font-mono tabular-nums text-navy-900 sm:px-6">{{ ReferralProgram::money($row['earnings']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($news->isNotEmpty())
        <section class="mt-6" aria-labelledby="news-heading">
            <div class="mb-3 flex items-center justify-between">
                <h2 id="news-heading" class="font-display text-base font-semibold text-navy-900">Latest from AffilStack</h2>
                <a href="{{ route('partner.news') }}" class="text-sm font-medium text-emerald-700 hover:text-emerald-800">All news</a>
            </div>
            <div class="grid gap-4 md:grid-cols-2">
                @foreach ($news as $item)
                    <a href="{{ route('partner.news') }}#news-{{ $item->id }}" class="block rounded-2xl border border-line bg-surface p-5 transition hover:border-emerald-200">
                        <p class="flex items-center gap-2 text-xs text-ink-400">
                            @if ($item->is_pinned)<span class="rounded-full bg-gold-300/30 px-2 py-0.5 font-medium text-gold-600">Pinned</span>@endif
                            {{ $item->published_at->format('M j, Y') }}
                        </p>
                        <p class="mt-1.5 font-display font-semibold text-navy-900">{{ $item->title }}</p>
                        <p class="mt-1 line-clamp-2 text-sm text-ink-600">{{ \Illuminate\Support\Str::limit(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $item->body)), ENT_QUOTES | ENT_HTML5))), 180) }}</p>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            var tiles = document.querySelectorAll('[data-metric]');
            tiles.forEach(function (tile) {
                tile.addEventListener('click', function () {
                    var key = tile.getAttribute('data-metric');
                    tiles.forEach(function (t) { t.setAttribute('aria-selected', t === tile ? 'true' : 'false'); });
                    document.querySelectorAll('[data-chart]').forEach(function (panel) {
                        panel.hidden = panel.getAttribute('data-chart') !== key;
                    });
                });
            });
        })();
    </script>
@endpush
