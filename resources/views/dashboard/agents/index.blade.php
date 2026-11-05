@extends('layouts.app')

@section('title', 'AI Agents')

@section('content')
    @php
        $agents = \App\Support\Agents\AgentSettings::AGENTS;
        $agentLabels = collect($agents)->map(fn ($a) => $a['label']);
    @endphp

    <p class="mb-6 max-w-2xl text-sm text-ink-600">
        Your agents work in the background so every product you add keeps selling. Switch each one on or off, and approve what they suggest.
        Agents use your credits only when they write something for you.
    </p>

    @unless ($included)
        <div class="mb-6 flex flex-col gap-3 rounded-2xl border border-gold-300 bg-gold-300/15 p-5 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-semibold text-navy-900">AI Agents aren't included in your current plan</p>
                <p class="text-sm text-ink-600">Upgrade to let Reply Copilot, Lead Hunter and the rest work for you around the clock.</p>
            </div>
            <x-ui.button variant="brand" :href="route('billing.index')" icon="arrow-up-circle">See plans</x-ui.button>
        </div>
    @endunless

    @if ($approvals->isNotEmpty())
        <section class="mb-8" aria-labelledby="approvals-heading">
            <h2 id="approvals-heading" class="mb-3 font-display text-lg font-semibold text-navy-900">Waiting for your approval</h2>
            <ul class="space-y-3">
                @foreach ($approvals as $item)
                    <li class="flex flex-col gap-3 rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">{{ $agentLabels[$item->agent] ?? $item->agent }}</p>
                            <p class="mt-0.5 text-sm text-ink-900">{{ $item->summary }}</p>
                            <p class="mt-0.5 text-xs text-ink-400">{{ $item->created_at->diffForHumans() }}</p>
                        </div>
                        <div class="flex shrink-0 gap-2">
                            <form method="POST" action="{{ route('agents.activities.approve', $item) }}">
                                @csrf
                                <button class="rounded-full bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                    {{ ($item->meta['approval'] ?? null) === 'research_program' ? 'Research it' : 'Approve' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('agents.activities.dismiss', $item) }}">
                                @csrf
                                <button class="rounded-full border border-line bg-white px-4 py-2 text-sm font-medium text-ink-700 hover:bg-surface-muted">Dismiss</button>
                            </form>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    <form method="POST" action="{{ route('agents.update') }}" class="mb-8">
        @csrf
        @method('PUT')
        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($agents as $key => $agent)
                @php($on = (bool) ($settings[$key]['enabled'] ?? false))
                <div class="flex flex-col rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                                <x-dynamic-component :component="'heroicon-o-'.$agent['icon']" class="h-5 w-5" />
                            </span>
                            <div>
                                <h3 class="font-display font-semibold text-navy-900">{{ $agent['label'] }}</h3>
                                <p class="mt-0.5 text-sm text-ink-600">{{ $agent['description'] }}</p>
                                @if (($weekCounts[$key] ?? 0) > 0)
                                    <p class="mt-1 text-xs font-medium text-emerald-700">{{ $weekCounts[$key] }} {{ \Illuminate\Support\Str::plural('action', $weekCounts[$key]) }} this week</p>
                                @endif
                            </div>
                        </div>
                        <label class="relative inline-flex shrink-0 cursor-pointer items-center">
                            <input type="checkbox" name="agents[{{ $key }}][enabled]" value="1" @checked($on) @disabled(! $included) class="peer sr-only">
                            <span class="h-6 w-11 rounded-full bg-ink-400/40 transition peer-checked:bg-emerald-600 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-500 peer-disabled:opacity-50"></span>
                            <span class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow transition peer-checked:translate-x-5"></span>
                            <span class="sr-only">Turn {{ $agent['label'] }} on</span>
                        </label>
                    </div>

                    @if ($key === 'reply_copilot')
                        <label class="mt-4 flex items-start gap-2 rounded-xl bg-surface-muted px-3 py-2.5 text-sm">
                            <input type="checkbox" name="agents[reply_copilot][autopilot]" value="1" @checked($settings['reply_copilot']['autopilot'] && $autopilotAllowed) @disabled(! $autopilotAllowed) class="mt-0.5 h-4 w-4 rounded border-line text-emerald-600 focus:ring-emerald-500">
                            <span>
                                <span class="font-medium text-navy-900">Autopilot for safe replies</span>
                                <span class="block text-xs text-ink-600">
                                    @if ($autopilotAllowed)
                                        Sends the reply by itself, a few minutes later, only when a lead is clearly interested or asks a plain question. Everything else waits for you.
                                    @else
                                        Available on Pro, Business and Agency plans.
                                    @endif
                                </span>
                            </span>
                        </label>
                    @elseif ($key === 'lead_hunter')
                        <label class="mt-4 flex items-center gap-2 text-sm text-ink-700">
                            Keep each product's list at
                            <input type="number" name="agents[lead_hunter][target]" min="10" max="1000" value="{{ $settings['lead_hunter']['target'] }}" class="w-24 rounded-lg border border-line px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            leads
                        </label>
                    @elseif ($key === 'content_autopilot')
                        <label class="mt-4 flex items-center gap-2 text-sm text-ink-700">
                            Write
                            <input type="number" name="agents[content_autopilot][per_week]" min="1" max="7" value="{{ $settings['content_autopilot']['per_week'] }}" class="w-16 rounded-lg border border-line px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            pieces a week (uses credits for each piece)
                        </label>
                    @endif
                </div>
            @endforeach
        </div>
        <div class="mt-4">
            <x-ui.button variant="brand" icon="check" :disabled="! $included">Save agent settings</x-ui.button>
        </div>
    </form>

    <section aria-labelledby="activity-heading">
        <h2 id="activity-heading" class="mb-3 font-display text-lg font-semibold text-navy-900">What your agents did</h2>
        @if ($activities->isEmpty())
            <p class="rounded-2xl border border-dashed border-line p-6 text-center text-sm text-ink-600">Nothing yet. Your agents' work shows up here as it happens.</p>
        @else
            <ul class="divide-y divide-line rounded-2xl border border-line bg-surface">
                @foreach ($activities as $item)
                    <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-start sm:justify-between sm:gap-4">
                        <div class="min-w-0">
                            <span class="text-xs font-semibold text-brand-700">{{ $agentLabels[$item->agent] ?? $item->agent }}</span>
                            @if ($item->status === 'approved')
                                <span class="ml-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700">Approved</span>
                            @elseif ($item->status === 'dismissed')
                                <span class="ml-1 rounded-full bg-surface-muted px-2 py-0.5 text-[11px] text-ink-600">Dismissed</span>
                            @endif
                            <p class="text-sm text-ink-900">{{ $item->summary }}</p>
                            @if (! empty($item->meta['apply_url']))
                                <a href="{{ $item->meta['apply_url'] }}" target="_blank" rel="noopener" class="text-xs font-medium text-brand-600 hover:text-brand-700">Apply on the network &rarr;</a>
                            @endif
                        </div>
                        <span class="shrink-0 text-xs text-ink-400">{{ $item->created_at->diffForHumans() }}</span>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@endsection
