{{-- "What AffilStack has done for you" — see App\Services\Dashboard\ProgressCounter. --}}
@php($milestone = $progress['milestone'])
<section class="mb-6 rounded-3xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] sm:p-6" aria-labelledby="progress-heading" data-tour="progress">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-[11px] font-mono uppercase tracking-[0.18em] text-brand-600">Your progress</p>
            <h2 id="progress-heading" class="mt-1 font-display text-xl font-semibold text-navy-900">What AffilStack has done for you</h2>
            <p class="mt-1 text-sm text-ink-600">
                @if ($progress['total'] > 0)
                    {{ number_format($progress['total']) }} things done for your business so far — and counting.
                @else
                    Research your first product and this fills up as AffilStack works for you.
                @endif
            </p>
        </div>
        <div class="w-full sm:w-64">
            <div class="flex items-center justify-between text-xs text-ink-600">
                <span>Next milestone</span>
                <span class="font-semibold text-navy-900 tabular-nums">{{ number_format($progress['total']) }} / {{ number_format($milestone['next']) }}</span>
            </div>
            <div class="mt-1.5 h-2.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuemin="{{ $milestone['previous'] }}" aria-valuemax="{{ $milestone['next'] }}" aria-valuenow="{{ $progress['total'] }}" aria-label="Progress to the next milestone">
                <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-gold-400 transition-all" style="width: {{ max(3, $milestone['percent']) }}%"></div>
            </div>
        </div>
    </div>

    <div class="mt-5 grid grid-cols-2 gap-3 md:grid-cols-4 xl:grid-cols-7">
        @foreach ($progress['tiles'] as $tile)
            <x-ui.stat :label="$tile['label']" :value="$tile['value']" :icon="$tile['icon']" :tone="$tile['tone']"
                       :hint="$tile['note'] ?? ($tile['week'] > 0 ? '+'.number_format($tile['week']).' this week' : null)" />
        @endforeach
    </div>
</section>
