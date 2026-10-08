{{-- Agent 04: horizontal bar list. Expects $rows (label, value, percent), optional $color, $unit, $empty. --}}
@php
    $barColor = $color ?? '#2452d9';
    $barMax = max(1, collect($rows)->max('value'));
@endphp
@if (empty($rows))
    <div class="afs-ins-empty">
        <x-filament::icon icon="heroicon-o-chart-bar" />
        <span>{{ $empty ?? 'No data for this period yet.' }}</span>
    </div>
@else
    <ul class="afs-ins-bars">
        @foreach ($rows as $row)
            <li class="afs-ins-bar-row">
                <span class="afs-ins-bar-label" title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                <span class="afs-ins-bar-num"><b>{{ number_format($row['value']) }}</b>{{ isset($unit) ? ' '.$unit : '' }} · {{ $row['percent'] }}%</span>
                <span class="afs-ins-bar-track" aria-hidden="true">
                    <span class="afs-ins-bar-fill" style="width: {{ max(2, round($row['value'] / $barMax * 100)) }}%; background: {{ $barColor }};"></span>
                </span>
            </li>
        @endforeach
    </ul>
@endif
