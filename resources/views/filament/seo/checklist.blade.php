{{-- On-page SEO checklist + score (see SeoScore). Inline styles: see search-appearance.blade.php. --}}
<?php
    $ringColor = $score >= 80 ? '#059669' : ($score >= 50 ? '#d97706' : '#dc2626');
    $verdict = $score >= 80 ? 'Good' : ($score >= 50 ? 'Needs work' : 'Poor');
    $icons = ['pass' => ['#059669', '✓'], 'warn' => ['#d97706', '!'], 'fail' => ['#dc2626', '✕']];
?>
<div>
    <div style="display: flex; align-items: center; gap: 14px; margin-bottom: 12px;">
        <div style="position: relative; width: 58px; height: 58px; flex-shrink: 0;">
            <div style="position: absolute; inset: 0; border-radius: 50%; background: conic-gradient({{ $ringColor }} {{ $score * 3.6 }}deg, rgba(127,127,127,.22) 0); -webkit-mask: radial-gradient(farthest-side, transparent calc(100% - 6px), #000 calc(100% - 5px)); mask: radial-gradient(farthest-side, transparent calc(100% - 6px), #000 calc(100% - 5px));"></div>
            <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 16px; color: {{ $ringColor }};">{{ $score }}</div>
        </div>
        <div>
            <div style="font-weight: 600; font-size: 15px;">SEO score: <span style="color: {{ $ringColor }};">{{ $verdict }}</span></div>
            <div style="font-size: 12px; opacity: .7;">Advisory only — nothing stops you saving. Fix the red items first.</div>
        </div>
    </div>
    <ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 8px;">
        @foreach ($checks as $check)
            <li style="display: flex; gap: 10px; align-items: flex-start;">
                <span aria-hidden="true" style="flex-shrink: 0; width: 18px; height: 18px; border-radius: 50%; background: {{ $icons[$check['status']][0] }}; color: #fff; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; margin-top: 1px;">{{ $icons[$check['status']][1] }}</span>
                <span style="font-size: 13px; line-height: 1.4;">
                    <span class="sr-only">{{ ['pass' => 'Passed:', 'warn' => 'Warning:', 'fail' => 'Failed:'][$check['status']] }}</span>
                    {{ $check['label'] }}
                    <span style="display: block; font-size: 12px; opacity: .65;">{{ $check['hint'] }}</span>
                </span>
            </li>
        @endforeach
    </ul>
</div>
