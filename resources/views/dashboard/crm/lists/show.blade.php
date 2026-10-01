@extends('layouts.app')

@section('title', $list->name)

@section('content')
    <nav class="mb-3 text-xs text-ink-600" aria-label="Breadcrumb">
        <a href="{{ route('crm.index') }}" class="hover:text-brand-600">CRM</a>
        <span class="mx-1 text-ink-400">/</span>
        <span class="text-ink-900">{{ $list->name }}</span>
    </nav>

    <x-ui.page-header
        :title="$list->name"
        :eyebrow="$list->isProductList() ? 'Product list' : 'Custom list'"
        icon="queue-list"
        :subtitle="$list->description ?: ($list->offer ? 'Prospects for '.$list->offer->product_name.' — campaigns sent here only ever reach this product\'s contacts.' : 'Your own hand-picked group of contacts.')">
        <x-slot:actions>
            <x-ui.button variant="gold" size="lg" icon="sparkles" :href="route('crm.campaigns.create', ['list' => $list->id])">Create Campaign</x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
        <x-ui.stat label="Contacts" :value="number_format($memberCount)" icon="users" />
        <x-ui.stat label="With an email address" :value="number_format($withEmailCount)" icon="envelope" tone="violet" :hint="$memberCount > 0 ? round($withEmailCount / $memberCount * 100).'% can be emailed' : null" />
        <x-ui.stat label="Replied" :value="number_format($repliedCount)" icon="chat-bubble-left-right" tone="emerald" />
        <x-ui.stat label="Campaigns" :value="$campaigns->count()" icon="megaphone" tone="gold" :hint="$campaigns->where('autopilot', true)->where('status', '!=', 'draft')->count().' on autopilot'" />
    </div>

    @if ($memberCount === 0)
        <x-ui.next-step class="mb-6" :href="route('leads.index', $list->offer_id ? ['offer' => $list->offer_id] : [])" action="Find local leads">
            This list is empty. Add prospects from Local Leads, the Chrome extension or a CSV import — then create a campaign for them.
        </x-ui.next-step>
    @elseif ($campaigns->isEmpty())
        <x-ui.next-step class="mb-6" :href="route('crm.campaigns.create', ['list' => $list->id])" action="Create Campaign">
            You have {{ number_format($memberCount) }} prospect{{ $memberCount === 1 ? '' : 's' }} here. Let AI write a personalised email for them.
        </x-ui.next-step>
    @endif

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
        <div class="min-w-0 space-y-4">
            <form method="GET" class="flex items-center gap-2" role="search">
                <label class="relative flex-1">
                    <span class="sr-only">Search this list</span>
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search this list" class="w-full rounded-lg border border-line bg-white py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </label>
                <x-ui.button variant="secondary">Search</x-ui.button>
                <a href="{{ route('crm.export', ['list' => $list->id]) }}" class="hidden sm:inline-flex items-center gap-1.5 px-2 text-sm text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-down-tray class="h-4 w-4" /> Export</a>
            </form>

            @include('dashboard.crm.partials.contacts-table', ['contacts' => $members, 'lists' => $lists, 'filtered' => (bool) request('q')])
        </div>

        <aside class="space-y-4">
            <x-ui.card title="Campaigns to this list" icon="megaphone" padding="p-3">
                @forelse ($campaigns as $campaign)
                    <a href="{{ route('crm.campaigns.show', $campaign) }}" class="block rounded-xl p-3 hover:bg-surface-muted transition">
                        <div class="flex items-start justify-between gap-2">
                            <span class="text-sm font-medium text-ink-900">{{ $campaign->name }}</span>
                            @include('dashboard.crm.partials.campaign-status', ['campaign' => $campaign])
                        </div>
                        <p class="mt-1 text-xs text-ink-600">
                            {{ number_format($campaign->sent_count) }} sent · {{ number_format($campaign->replied_count) }} replied
                        </p>
                        @if ($campaign->new_contacts_count > 0)
                            <p class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-gold-300/25 px-2 py-0.5 text-[11px] font-medium text-gold-600">
                                <x-heroicon-m-user-plus class="h-3 w-3" /> {{ $campaign->new_contacts_count }} new contact{{ $campaign->new_contacts_count === 1 ? '' : 's' }} not sent yet
                            </p>
                        @endif
                    </a>
                @empty
                    <p class="px-2 py-3 text-sm text-ink-600">No campaigns yet. Each campaign you send to this list — and who it reached — is kept here.</p>
                @endforelse
            </x-ui.card>

            <x-ui.card title="List settings" icon="cog-6-tooth">
                <form method="POST" action="{{ route('crm.lists.update', $list) }}" class="space-y-3">
                    @csrf @method('PATCH')
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Name</span>
                        <input name="name" value="{{ old('name', $list->name) }}" required maxlength="100" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Description <span class="text-ink-400">(helps the AI)</span></span>
                        <textarea name="description" rows="2" maxlength="500" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('description', $list->description) }}</textarea>
                    </label>
                    <fieldset>
                        <legend class="block text-xs font-medium text-ink-600 mb-1.5">Colour</legend>
                        <div class="flex flex-wrap gap-1.5">
                            @foreach ($listColors as $color)
                                <label class="cursor-pointer">
                                    <input type="radio" name="color" value="{{ $color }}" class="peer sr-only" @checked($list->color === $color)>
                                    <span class="block h-6 w-6 rounded-full {{ (new \App\Models\CrmList(['color' => $color]))->dotClass() }} ring-2 ring-transparent ring-offset-2 peer-checked:ring-navy-900 peer-focus-visible:ring-brand-500" title="{{ ucfirst($color) }}"></span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <x-ui.button size="sm" class="w-full">Save list</x-ui.button>
                </form>
                @unless ($list->isProductList() && $list->offer)
                    <form method="POST" action="{{ route('crm.lists.destroy', $list) }}" class="mt-3" onsubmit="return confirm('Delete this list and its campaign history? The contacts themselves stay in your CRM.')">
                        @csrf @method('DELETE')
                        <button class="w-full rounded-full px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Delete list</button>
                    </form>
                @endunless
            </x-ui.card>
        </aside>
    </div>
@endsection
