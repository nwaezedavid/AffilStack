@extends('layouts.app')

@section('title', 'Campaigns')

@section('content')
    <x-ui.page-header title="Campaigns" eyebrow="CRM" icon="megaphone"
        subtitle="Every campaign you've written or sent, with who it reached and how they responded.">
        <x-slot:actions>
            <x-ui.button variant="gold" icon="sparkles" :href="route('crm.campaigns.create')">Create Campaign</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    @if ($campaigns->isEmpty())
        <x-ui.empty-state icon="megaphone" title="No campaigns yet" :href="route('crm.campaigns.create')" action="Create your first campaign">
            Pick a list of prospects and AI writes a personalised email for the product you're promoting. You approve it before anything is sent.
        </x-ui.empty-state>
    @else
        <div class="grid gap-4 md:grid-cols-2">
            @foreach ($campaigns as $campaign)
                @php($rate = fn ($n) => $campaign->sent_count > 0 ? round($n / $campaign->sent_count * 100) : 0)
                <a href="{{ route('crm.campaigns.show', $campaign) }}" class="group rounded-2xl border border-line bg-surface p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)] transition hover:-translate-y-0.5 hover:shadow-md focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="truncate font-display font-semibold text-navy-900 group-hover:text-brand-700">{{ $campaign->name }}</h3>
                            <p class="mt-0.5 flex items-center gap-1.5 text-xs text-ink-600">
                                <span class="h-2 w-2 rounded-full {{ $campaign->list?->dotClass() }}" aria-hidden="true"></span>
                                {{ $campaign->list?->name }}
                            </p>
                        </div>
                        @include('dashboard.crm.partials.campaign-status', ['campaign' => $campaign])
                    </div>
                    <p class="mt-3 truncate text-sm text-ink-900">“{{ $campaign->subject }}”</p>
                    @if ($campaign->isDraft())
                        <p class="mt-4 text-xs text-ink-400">Draft · not sent yet · edited {{ $campaign->updated_at->diffForHumans() }}</p>
                    @else
                        <dl class="mt-4 grid grid-cols-4 gap-2 text-center">
                            @foreach ([['Sent', number_format($campaign->sent_count)], ['Opened', $rate($campaign->opened_count).'%'], ['Replied', $rate($campaign->replied_count).'%'], ['Total', number_format($campaign->recipients_count)]] as [$label, $value])
                                <div class="rounded-lg bg-surface-muted/70 py-2">
                                    <dt class="text-[10px] font-mono uppercase tracking-[0.12em] text-ink-400">{{ $label }}</dt>
                                    <dd class="font-display text-base font-semibold text-navy-900 tabular-nums">{{ $value }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </a>
            @endforeach
        </div>
        <div class="mt-4">{{ $campaigns->links() }}</div>
    @endif
@endsection
