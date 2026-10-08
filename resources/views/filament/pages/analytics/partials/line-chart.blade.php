{{--
    Agent 04: daily traffic line/area chart (inline SVG, no JS). Expects
    $series: [{label, views, sessions, visitors}].
--}}
@php
    $w = 720; $h = 220; $padL = 36; $padR = 12; $padT = 12; $padB = 26;
    $n = count($series);
    $maxY = max(1, collect($series)->max('views'), collect($series)->max('sessions'));
    $niceMax = (int) (ceil($maxY / max(1, 10 ** floor(log10($maxY)))) * max(1, 10 ** floor(log10($maxY))));
    $niceMax = max($niceMax, 4);
    $x = fn ($i) => $padL + ($n > 1 ? $i * ($w - $padL - $padR) / ($n - 1) : ($w - $padL - $padR) / 2);
    $y = fn ($v) => $padT + ($h - $padT - $padB) * (1 - $v / $niceMax);
    $line = fn ($key) => collect($series)->values()->map(fn ($p, $i) => round($x($i), 1).','.round($y($p[$key]), 1))->implode(' ');
    $area = $n ? 'M'.round($x(0), 1).','.round($y(0), 1).' L'.collect($series)->values()->map(fn ($p, $i) => round($x($i), 1).','.round($y($p['views']), 1))->implode(' L').' L'.round($x($n - 1), 1).','.round($y(0), 1).' Z' : '';
    $labelEvery = max(1, (int) ceil($n / 8));
    $totalViews = collect($series)->sum('views');
@endphp
<div class="afs-ins-legend" style="margin-bottom: .5rem;">
    <span><i class="afs-ins-swatch" style="background:#2452d9"></i> Page views (all)</span>
    <span><i class="afs-ins-swatch" style="background:#c9a24a"></i> Visits (sessions)</span>
    <span><i class="afs-ins-swatch" style="background:#0d9488"></i> Visitors</span>
</div>
<svg class="afs-ins-chart" viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="Daily page views, visits and visitors. {{ number_format($totalViews) }} page views in total.">
    <defs>
        <linearGradient id="afsInsArea" x1="0" x2="0" y1="0" y2="1">
            <stop offset="0%" stop-color="#2452d9" stop-opacity=".28" />
            <stop offset="100%" stop-color="#2452d9" stop-opacity="0" />
        </linearGradient>
    </defs>
    @foreach ([0, 0.25, 0.5, 0.75, 1] as $tick)
        <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ round($y($niceMax * $tick), 1) }}" y2="{{ round($y($niceMax * $tick), 1) }}" stroke="currentColor" stroke-opacity=".08" />
        <text x="{{ $padL - 6 }}" y="{{ round($y($niceMax * $tick), 1) + 3 }}" text-anchor="end" font-size="10" fill="currentColor" opacity=".5">{{ number_format($niceMax * $tick) }}</text>
    @endforeach
    @if ($n)
        <path d="{{ $area }}" fill="url(#afsInsArea)" />
        <polyline points="{{ $line('views') }}" fill="none" stroke="#2452d9" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" />
        <polyline points="{{ $line('sessions') }}" fill="none" stroke="#c9a24a" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" />
        <polyline points="{{ $line('visitors') }}" fill="none" stroke="#0d9488" stroke-width="1.75" stroke-dasharray="4 3" stroke-linejoin="round" />
        @foreach ($series as $i => $point)
            <circle cx="{{ round($x($i), 1) }}" cy="{{ round($y($point['views']), 1) }}" r="{{ $n > 45 ? 0 : 2.5 }}" fill="#2452d9"><title>{{ $point['label'] }}: {{ $point['views'] }} page views, {{ $point['sessions'] }} visits, {{ $point['visitors'] }} visitors</title></circle>
            @if ($i % $labelEvery === 0 || ($i === $n - 1 && $i % $labelEvery >= $labelEvery / 2))
                <text x="{{ round($x($i), 1) }}" y="{{ $h - 8 }}" text-anchor="middle" font-size="10" fill="currentColor" opacity=".55">{{ $point['label'] }}</text>
            @endif
        @endforeach
    @endif
</svg>
