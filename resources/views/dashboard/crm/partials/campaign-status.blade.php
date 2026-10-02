@php
    $styles = [
        'draft' => ['bg-surface-muted text-ink-600 ring-line', 'Draft'],
        'approved' => ['bg-brand-50 text-brand-700 ring-brand-600/20', 'Approved'],
        'sending' => ['bg-amber-50 text-amber-700 ring-amber-600/20', 'Sending'],
        'sent' => ['bg-emerald-50 text-emerald-700 ring-emerald-600/20', 'Sent'],
        'paused' => ['bg-rose-50 text-rose-700 ring-rose-600/20', 'Paused'],
    ];
    [$classes, $label] = $styles[$campaign->status] ?? $styles['draft'];
@endphp
<span class="inline-flex shrink-0 items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $classes }}">
    @if ($campaign->autopilot && $campaign->status !== 'draft')
        <x-heroicon-m-bolt class="h-3 w-3" aria-label="Autopilot on" />
    @endif
    {{ $label }}
</span>
