@props(['values' => [], 'labels' => [], 'color' => '#059669', 'money' => false, 'label' => 'Chart'])

@php
    $values = array_values($values);
    $count = count($values);
    $max = max(1, ...($values ?: [0]));
    // Round the top of the scale to a friendly number.
    $magnitude = 10 ** max(0, floor(log10($max)));
    $niceMax = (int) (ceil($max / $magnitude * 2) / 2 * $magnitude) ?: 1;
    $w = 600;
    $h = 180;
    $x = fn (int $i) => $count > 1 ? round($i / ($count - 1) * $w, 2) : $w / 2;
    $y = fn (int|float $v) => round($h - ($v / $niceMax) * ($h - 8), 2);
    $points = collect($values)->map(fn ($v, $i) => $x($i).','.$y($v))->implode(' ');
    $area = $count > 0 ? 'M0,'.$h.' L'.collect($values)->map(fn ($v, $i) => $x($i).','.$y($v))->implode(' L').' L'.$w.','.$h.' Z' : '';
    $format = fn ($v) => $money ? \App\Support\ReferralProgram::money((int) $v) : number_format($v);
    $tickEvery = max(1, (int) ceil($count / 6));
    $gradientId = 'g'.substr(md5($label.$color), 0, 8);
    $lastIndex = $count - 1;
@endphp

<figure {{ $attributes->merge(['class' => 'w-full']) }}>
    <div class="flex gap-3">
        <div class="flex flex-col justify-between py-0.5 text-right text-[10px] font-mono tabular-nums text-ink-400 w-12 shrink-0" aria-hidden="true">
            <span>{{ $format($niceMax) }}</span>
            <span>{{ $format($niceMax / 2) }}</span>
            <span>0</span>
        </div>
        <div class="relative flex-1 min-w-0">
            <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" class="block h-44 w-full overflow-visible" role="img" aria-label="{{ $label }}">
                <defs>
                    <linearGradient id="{{ $gradientId }}" x1="0" x2="0" y1="0" y2="1">
                        <stop offset="0%" stop-color="{{ $color }}" stop-opacity="0.28" />
                        <stop offset="100%" stop-color="{{ $color }}" stop-opacity="0" />
                    </linearGradient>
                </defs>
                @foreach ([0, 0.5, 1] as $line)
                    <line x1="0" x2="{{ $w }}" y1="{{ $y($niceMax * $line) }}" y2="{{ $y($niceMax * $line) }}" stroke="currentColor" class="text-line" stroke-width="1" vector-effect="non-scaling-stroke" stroke-dasharray="{{ $line === 0 ? '0' : '3 4' }}" />
                @endforeach
                @if ($count > 0)
                    <path d="{{ $area }}" fill="url(#{{ $gradientId }})" />
                    <polyline points="{{ $points }}" fill="none" stroke="{{ $color }}" stroke-width="2.25" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                @endif
            </svg>
            {{-- Hover targets and the end point live in HTML so they keep their shape on any width. --}}
            <div class="absolute inset-0 flex">
                @foreach ($values as $i => $value)
                    <div class="group relative flex-1" tabindex="0" aria-label="{{ $labels[$i] ?? '' }}: {{ $format($value) }}">
                        <span class="pointer-events-none absolute bottom-0 left-1/2 top-0 w-px -translate-x-1/2 bg-ink-400/30 opacity-0 group-hover:opacity-100 group-focus:opacity-100"></span>
                        @php
                            $align = $i < $count / 4 ? 'left-0' : ($i > $count * 3 / 4 ? 'right-0' : 'left-1/2 -translate-x-1/2');
                        @endphp
                        <span class="pointer-events-none absolute {{ $align }} z-10 -translate-y-full whitespace-nowrap rounded-md bg-navy-950 px-2 py-1 text-[11px] text-white opacity-0 shadow-lg group-hover:opacity-100 group-focus:opacity-100" style="top: {{ round($y($value) / $h * 100, 2) }}%">
                            {{ $labels[$i] ?? '' }} · <strong class="font-semibold">{{ $format($value) }}</strong>
                        </span>
                    </div>
                @endforeach
            </div>
            @if ($count > 0)
                <span class="pointer-events-none absolute h-2.5 w-2.5 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-white" style="left: {{ $count > 1 ? 100 : 50 }}%; top: {{ round($y($values[$lastIndex]) / $h * 100, 2) }}%; background: {{ $color }}" aria-hidden="true"></span>
            @endif
        </div>
    </div>
    <div class="mt-2 flex justify-between text-[10px] font-mono text-ink-400" aria-hidden="true" style="margin-left: 3.75rem">
        @foreach ($labels as $i => $text)
            @if ($i % $tickEvery === 0 || $i === $lastIndex)
                <span class="{{ $i !== 0 && $i !== $lastIndex ? 'hidden sm:inline' : '' }}">{{ $text }}</span>
            @endif
        @endforeach
    </div>
</figure>
