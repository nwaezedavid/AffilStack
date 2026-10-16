@php
    $networkPrograms = app(\App\Services\Networks\NetworkLinkService::class)->candidatesFor($offer);
    $linkedProgram = $offer->networkProgram;
    $linkService = app(\App\Services\Networks\NetworkLinkService::class);
@endphp

@if ($networkPrograms->isNotEmpty() || $offer->network_link)
    <div class="mb-6 rounded-2xl border border-line bg-white p-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600"><x-heroicon-o-link class="h-5 w-5" /></span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-navy-900">Tracked link</p>
                    @if ($offer->use_network_link && $offer->network_link)
                        <p class="text-xs text-ink-600">Your content uses the {{ $linkedProgram?->connection?->label() ?? 'network' }} tracked link{{ $linkedProgram ? ' for '.$linkedProgram->name : '' }}. Each piece of content passes its own code as a sub-ID, so sales are traced back to it.</p>
                        <p class="mt-1 truncate font-mono text-[11px] text-ink-400" title="{{ $offer->network_link }}">{{ $offer->network_link }}</p>
                    @elseif ($offer->network_link)
                        <p class="text-xs text-ink-600">Your content uses your own affiliate link. The {{ $linkedProgram?->connection?->label() ?? 'network' }} tracked link is saved if you want to switch back.</p>
                    @else
                        <p class="text-xs text-ink-600">Use a tracked link from one of your connected networks. Your own affiliate link stays as the backup.</p>
                    @endif
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-2">
                @if ($offer->network_link)
                    <form method="POST" action="{{ route('offers.network-link.update', $offer) }}">
                        @csrf
                        @method('PATCH')
                        <input type="hidden" name="use_network_link" value="{{ $offer->use_network_link ? 0 : 1 }}">
                        <x-ui.button size="sm" variant="secondary" icon="arrows-right-left">{{ $offer->use_network_link ? 'Use my own link' : 'Use network link' }}</x-ui.button>
                    </form>
                @endif
            </div>
        </div>

        @if ($networkPrograms->isNotEmpty())
            <form method="POST" action="{{ route('offers.network-link.store', $offer) }}" class="mt-3 flex flex-col gap-2 border-t border-line pt-3 sm:flex-row sm:items-center">
                @csrf
                <label for="network-program-{{ $offer->id }}" class="text-xs text-ink-600 sm:shrink-0">{{ $offer->network_link ? 'Change program' : 'Network program' }}</label>
                <select id="network-program-{{ $offer->id }}" name="program_id" class="min-w-0 flex-1 rounded-full border border-line bg-white px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @foreach ($networkPrograms as $program)
                        <option value="{{ $program->id }}" @selected($linkedProgram?->id === $program->id)>{{ $program->name }} · {{ $program->connection->label() }}{{ $linkService->matchesWebsite($offer, $program) ? ' (same website)' : '' }}</option>
                    @endforeach
                </select>
                <x-ui.button size="sm" icon="check">{{ $offer->network_link ? 'Get new link' : 'Use this program\'s link' }}</x-ui.button>
            </form>
        @endif
    </div>
@endif
