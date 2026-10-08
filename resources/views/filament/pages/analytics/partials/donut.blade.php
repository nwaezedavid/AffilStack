{{-- Agent 04: donut chart + legend. Expects $rows (label, value, percent), optional $title for the centre. --}}
@php
    $palette = ['#2452d9', '#c9a24a', '#0d9488', '#7c3aed', '#e11d48', '#d97706', '#0891b2', '#64748b'];
    $circumference = 2 * M_PI * 40;
    $offset = 0;
    $donutTotal = collect($rows)->sum('value');
@endphp
@if (empty($rows) || $donutTotal === 0)
    <div class="afs-ins-empty">
        <x-filament::icon icon="heroicon-o-chart-pie" />
        <span>{{ $empty ?? 'No data for this period yet.' }}</span>
    </div>
@else
    <div class="afs-ins-donut-wrap">
        <svg class="afs-ins-donut" viewBox="0 0 100 100" role="img" aria-label="{{ collect($rows)->map(fn ($r) => $r['label'].' '.$r['percent'].'%')->implode(', ') }}">
            <circle cx="50" cy="50" r="40" fill="none" stroke="currentColor" stroke-opacity=".08" stroke-width="14" />
            @foreach ($rows as $i => $row)
                @php($length = $row['value'] / $donutTotal * $circumference)
                <circle cx="50" cy="50" r="40" fill="none" stroke="{{ $palette[$i % count($palette)] }}" stroke-width="14"
                        stroke-dasharray="{{ round($length, 3) }} {{ round($circumference - $length, 3) }}"
                        stroke-dashoffset="{{ round(-$offset, 3) }}" transform="rotate(-90 50 50)" />
                @php($offset += $length)
            @endforeach
            <text x="50" y="48" text-anchor="middle" font-size="14" font-weight="700" fill="currentColor">{{ number_format($donutTotal) }}</text>
            <text x="50" y="61" text-anchor="middle" font-size="7" fill="currentColor" opacity=".6">{{ $title ?? 'total' }}</text>
        </svg>
        <ul class="afs-ins-bars" style="flex: 1; min-width: 10rem;">
            @foreach ($rows as $i => $row)
                <li class="afs-ins-bar-row">
                    <span class="afs-ins-bar-label"><span class="afs-ins-swatch" style="background: {{ $palette[$i % count($palette)] }}; margin-right: .4rem;"></span>{{ ucfirst($row['label']) }}</span>
                    <span class="afs-ins-bar-num"><b>{{ $row['percent'] }}%</b></span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
