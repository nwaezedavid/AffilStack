{{-- One "generate" action as a card: what it makes, its cost, the button.
     Expects $action => [route, title, description, cost, icon, disabledReason?]. --}}
@php([$actionRoute, $actionTitle, $actionDescription, $actionCost, $actionIcon] = $action)
@php($disabledReason = $action[5] ?? null)
<form method="POST" action="{{ $actionRoute }}" class="flex flex-col rounded-2xl border border-line bg-white p-4 transition hover:shadow-md">
    @csrf
    <div class="flex items-start gap-3">
        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl {{ $tone['soft'] }}">
            <x-dynamic-component :component="'heroicon-o-'.$actionIcon" class="h-5 w-5" />
        </span>
        <div class="min-w-0">
            <p class="font-semibold text-navy-900">{{ $actionTitle }}</p>
            <p class="mt-0.5 text-sm text-ink-600">{{ $actionDescription }}</p>
        </div>
    </div>
    <div class="mt-4 flex items-center justify-between gap-2">
        <span class="inline-flex items-center gap-1 text-xs text-ink-600"><x-heroicon-m-bolt class="h-3.5 w-3.5 text-gold-500" /> {{ $actionCost }} credits</span>
        <x-ui.button variant="primary" size="sm" icon="sparkles" :disabled="(bool) $disabledReason" :title="$disabledReason">Generate</x-ui.button>
    </div>
    @if ($disabledReason)
        <p class="mt-2 text-xs text-ink-400">{{ $disabledReason }}</p>
    @endif
</form>
