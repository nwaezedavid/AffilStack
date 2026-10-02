{{-- Favicon / search-engine readiness checks (see FaviconHealth). --}}
<?php $icons = ['pass' => ['#059669', '✓', 'Passed:'], 'warn' => ['#d97706', '!', 'Warning:'], 'fail' => ['#dc2626', '✕', 'Problem:']]; ?>
<ul style="list-style: none; margin: 0; padding: 0; display: grid; gap: 10px;">
    @foreach ($checks as $check)
        <li style="display: flex; gap: 10px; align-items: flex-start;">
            <span aria-hidden="true" style="flex-shrink: 0; width: 20px; height: 20px; border-radius: 50%; background: {{ $icons[$check['status']][0] }}; color: #fff; font-size: 12px; font-weight: 700; display: inline-flex; align-items: center; justify-content: center;">{{ $icons[$check['status']][1] }}</span>
            <span style="font-size: 13px; line-height: 1.45;">
                <span class="sr-only">{{ $icons[$check['status']][2] }}</span>
                <strong style="font-weight: 600;">{{ $check['label'] }}</strong>
                <span style="display: block; font-size: 12px; opacity: .7;">{{ $check['hint'] }}</span>
            </span>
        </li>
    @endforeach
</ul>
