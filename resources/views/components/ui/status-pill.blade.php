@props(['status' => 'not_connected', 'label' => null])

{{-- One vocabulary for every "is this connected / did it work" indicator in
     the dashboard, so a user learns it once:
     verified  — connected and the last live check passed
     connected — connected, not yet checked
     checking  — a check is running
     failed    — the last check failed (see the message next to it)
     expired   — was working, needs reconnecting
     not_connected — nothing set up yet --}}
@php
    $map = [
        'verified' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'bg-emerald-500', 'Verified'],
        'connected' => ['bg-brand-50 text-brand-700 ring-brand-600/20', 'bg-brand-500', 'Connected'],
        'checking' => ['bg-amber-50 text-amber-700 ring-amber-600/20', 'bg-amber-500 animate-pulse', 'Checking…'],
        'failed' => ['bg-red-50 text-red-700 ring-red-600/20', 'bg-red-500', 'Check failed'],
        'expired' => ['bg-orange-50 text-orange-700 ring-orange-600/20', 'bg-orange-500', 'Reconnect needed'],
        'not_connected' => ['bg-surface-muted text-ink-600 ring-line', 'bg-ink-400', 'Not connected'],
    ];
    [$classes, $dot, $default] = $map[$status] ?? $map['not_connected'];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset '.$classes]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}" aria-hidden="true"></span>
    {{ $label ?? $default }}
</span>
