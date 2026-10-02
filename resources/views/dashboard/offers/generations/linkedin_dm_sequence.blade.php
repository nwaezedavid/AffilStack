<div class="mb-3 flex flex-wrap items-center gap-2">
    @if ($gen->published_at)
        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700"><x-heroicon-m-check-circle class="h-3.5 w-3.5" /> Started {{ $gen->published_at->diffForHumans() }}</span>
        <span class="text-xs text-ink-600">Follow-up reminders are on your <a href="{{ route('calendar.index') }}" class="font-medium text-brand-600 hover:text-brand-700">content calendar</a>.</span>
    @elseif (auth()->user()->isSeat())
        <span class="text-xs text-ink-600">Not started yet — ask the account owner to mark this as started.</span>
    @else
        <form method="POST" action="{{ route('generations.nurture-started', $gen) }}">
            @csrf
            <x-ui.button variant="secondary" size="sm" icon="calendar-days">Mark as started today — get follow-up reminders</x-ui.button>
        </form>
    @endif
</div>
<ol class="space-y-2">
    @foreach ($gen->output_meta['messages'] ?? [] as $msg)
        <li class="rounded-2xl bg-white p-3.5 ring-1 ring-line">
            <div class="flex items-center justify-between gap-2">
                <span class="text-xs font-semibold text-sky-700">Step {{ $msg['step'] ?? '' }} · {{ $msg['send_timing'] ?? '' }}</span>
                <x-ui.copy-button :text="$msg['message'] ?? ''" size="xs" />
            </div>
            <p class="mt-1.5 whitespace-pre-line text-sm text-ink-900">{{ $msg['message'] ?? '' }}</p>
        </li>
    @endforeach
</ol>
