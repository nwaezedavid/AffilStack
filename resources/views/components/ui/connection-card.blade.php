@props([
    'brand',
    'name',
    'description' => null,
    'status' => 'not_connected',
    'statusLabel' => null,
    'account' => null,
    'checkedAt' => null,
    'message' => null,
])

{{-- A third-party connection: brand mark, what it's for, live status, and
     the connect/verify/disconnect controls (passed in the default slot). --}}
<div {{ $attributes->merge(['class' => 'group relative flex flex-col rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition hover:shadow-md hover:-translate-y-0.5']) }}>
    <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3 min-w-0">
            <span class="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-surface-muted ring-1 ring-line">
                <x-ui.brand-icon :name="$brand" class="h-6 w-6" />
            </span>
            <div class="min-w-0">
                <h3 class="font-display font-semibold text-[15px] text-navy-900 truncate">{{ $name }}</h3>
                @if ($account)
                    <p class="text-xs text-ink-600 truncate">{{ $account }}</p>
                @endif
            </div>
        </div>
        <x-ui.status-pill :status="$status" :label="$statusLabel" />
    </div>

    @if ($description)
        <p class="mt-3 text-sm text-ink-600">{{ $description }}</p>
    @endif

    @if ($message)
        <p @class([
            'mt-3 rounded-lg px-3 py-2 text-xs',
            'bg-red-50 text-red-700' => $status === 'failed' || $status === 'expired',
            'bg-emerald-50 text-emerald-700' => $status === 'verified',
            'bg-surface-muted text-ink-600' => ! in_array($status, ['failed', 'expired', 'verified'], true),
        ])>{{ $message }}</p>
    @endif

    <div class="mt-auto pt-4 flex flex-wrap items-center gap-2">
        {{ $slot }}
    </div>

    @if ($checkedAt)
        <p class="mt-3 text-[11px] text-ink-400">Last checked {{ $checkedAt instanceof \DateTimeInterface ? \Illuminate\Support\Carbon::instance($checkedAt)->diffForHumans() : $checkedAt }}</p>
    @endif
</div>
