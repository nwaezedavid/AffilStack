@extends('layouts.app')

@section('title', 'CRM Contacts')

@section('content')
    <x-ui.page-header
        title="CRM & Campaigns"
        eyebrow="Find & close buyers"
        icon="users"
        subtitle="Every prospect, organised into lists by the product you're promoting — so each campaign only ever reaches the right people.">
        <x-slot:actions>
            <x-ui.button variant="gold" icon="sparkles" :href="route('crm.campaigns.create', $activeList ? ['list' => $activeList->id] : [])">Create Campaign</x-ui.button>
            <x-ui.button variant="light" icon="inbox-arrow-down" :href="route('crm.inbox')">
                Inbox
                @if ($unreadReplies > 0)
                    <span class="ml-0.5 inline-flex min-w-5 h-5 items-center justify-center rounded-full bg-rose-500 px-1.5 text-[11px] font-semibold text-white">{{ $unreadReplies }}</span>
                @endif
            </x-ui.button>
        </x-slot:actions>
    </x-ui.page-header>

    <div class="grid grid-cols-2 lg:grid-cols-5 gap-3 mb-4">
        <x-ui.stat label="Total contacts" :value="number_format($stats['total'])" icon="users" />
        <x-ui.stat label="Contacted" :value="number_format($stats['by_status']['contacted'])" icon="paper-airplane" tone="violet" />
        <x-ui.stat label="Replied" :value="number_format($stats['replied'])" icon="chat-bubble-left-right" tone="emerald" />
        <x-ui.stat label="Customers" :value="number_format($stats['by_status']['customer'])" icon="trophy" tone="gold" />
        <x-ui.stat label="Emails this month" :value="number_format($stats['sent_this_month'])" icon="envelope" tone="rose"
                   :hint="$stats['open_rate_this_month'] !== null ? $stats['open_rate_this_month'].'% opened' : 'No sends yet'" />
    </div>

    <div class="flex flex-wrap items-center gap-2 mb-6 text-xs text-ink-600">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1">
            <x-heroicon-m-envelope class="h-3.5 w-3.5 text-ink-400" /> {{ number_format($stats['sent_this_month']) }} email{{ $stats['sent_this_month'] === 1 ? '' : 's' }} sent this month
        </span>
        @if ($stats['open_rate_this_month'] !== null)
            <span class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1">
                <x-heroicon-m-eye class="h-3.5 w-3.5 text-ink-400" /> {{ $stats['open_rate_this_month'] }}% open rate this month
            </span>
        @endif
        @foreach (['new', 'qualified', 'unqualified'] as $status)
            <a href="{{ route('crm.index', ['status' => $status]) }}" class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-3 py-1 hover:bg-brand-50 hover:text-brand-700 transition">{{ ucfirst($status) }}: {{ number_format($stats['by_status'][$status]) }}</a>
        @endforeach
    </div>

    <x-ui.feature-intro class="mb-6" :open="$stats['total'] === 0" :steps="[
        'Save prospects into the product\'s list — from Local Leads, the Chrome extension, a CSV, or by hand.',
        'Click Create Campaign: AI writes a personalised cold email for that product. Edit it, then Approve and Send.',
        'Turn on Autopilot and every new contact on the list gets the email automatically.',
        'Replies land in your Inbox — let AI draft the follow-up that closes the sale.',
    ]">
        Your CRM is where prospects become buyers. Lists keep each product's contacts separate so you never send the wrong offer to the wrong person.
    </x-ui.feature-intro>

    <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
        {{-- Lists sidebar --}}
        <aside class="space-y-4" aria-label="Contact lists">
            @include('dashboard.crm.partials.lists-sidebar', ['lists' => $lists, 'activeList' => $activeList, 'listColors' => $listColors])

            @if ($recentCampaigns->isNotEmpty())
                <x-ui.card title="Recent campaigns" icon="megaphone" padding="p-2">
                    <ul class="divide-y divide-line">
                        @foreach ($recentCampaigns as $campaign)
                            <li>
                                <a href="{{ route('crm.campaigns.show', $campaign) }}" class="flex items-center justify-between gap-2 rounded-lg px-3 py-2.5 hover:bg-surface-muted transition">
                                    <span class="min-w-0">
                                        <span class="block truncate text-sm font-medium text-ink-900">{{ $campaign->name }}</span>
                                        <span class="block truncate text-xs text-ink-400">{{ $campaign->list?->name }}</span>
                                    </span>
                                    @include('dashboard.crm.partials.campaign-status', ['campaign' => $campaign])
                                </a>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('crm.campaigns.index') }}" class="block px-3 py-2 text-xs font-medium text-brand-600 hover:text-brand-700">All campaigns &rarr;</a>
                </x-ui.card>
            @endif
        </aside>

        <div class="min-w-0 space-y-4">
            {{-- Add / import --}}
            <div class="grid gap-3 md:grid-cols-2">
                <details class="group rounded-2xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]" @if ($errors->hasAny(['name', 'email', 'website'])) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-user-plus class="h-5 w-5" /></span>
                        <span class="flex-1">
                            <span class="block font-display font-semibold text-sm text-navy-900">Add a contact</span>
                            <span class="block text-xs text-ink-600">Type one in by hand</span>
                        </span>
                        <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('crm.store') }}" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                        @csrf
                        @foreach ([['name', 'Name', 'text'], ['company', 'Company', 'text'], ['email', 'Email', 'email'], ['phone', 'Phone', 'text'], ['website', 'Website (https://…)', 'url'], ['title', 'Job title', 'text']] as [$field, $label, $type])
                            <label class="block">
                                <span class="sr-only">{{ $label }}</span>
                                <input name="{{ $field }}" type="{{ $type }}" value="{{ old($field) }}" placeholder="{{ $label }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </label>
                        @endforeach
                        <label class="block sm:col-span-2">
                            <span class="sr-only">Location</span>
                            <input name="location" value="{{ old('location') }}" placeholder="Location (e.g. Ikeja, Lagos)" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        </label>
                        <label class="block sm:col-span-2">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Add to list</span>
                            <select name="list_id" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">No list</option>
                                @foreach ($lists as $list)
                                    <option value="{{ $list->id }}" @selected(old('list_id', $activeList?->id) == $list->id)>{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <textarea name="notes" placeholder="Notes" rows="2" class="sm:col-span-2 rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('notes') }}</textarea>
                        <x-ui.button class="sm:col-span-2" icon="check">Save contact</x-ui.button>
                    </form>
                </details>

                <details class="group rounded-2xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]" @if ($errors->hasAny(['file', 'list_id'])) open @endif>
                    <summary class="flex cursor-pointer list-none items-center gap-3">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600"><x-heroicon-o-arrow-up-tray class="h-5 w-5" /></span>
                        <span class="flex-1">
                            <span class="block font-display font-semibold text-sm text-navy-900">Import a CSV</span>
                            <span class="block text-xs text-ink-600">From a spreadsheet, into a list</span>
                        </span>
                        <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-open:rotate-180" />
                    </summary>
                    <form method="POST" action="{{ route('crm.import.upload') }}" enctype="multipart/form-data" class="mt-4 space-y-3">
                        @csrf
                        <label class="block">
                            <span class="block text-xs font-medium text-ink-600 mb-1">CSV file (with a header row)</span>
                            <input type="file" name="file" accept=".csv,text/csv" required class="block w-full text-sm text-ink-600 file:mr-3 file:rounded-full file:border-0 file:bg-navy-900 file:px-4 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-navy-800">
                        </label>
                        <label class="block">
                            <span class="block text-xs font-medium text-ink-600 mb-1">Import into list</span>
                            <select name="list_id" required class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                <option value="">Choose a list…</option>
                                @foreach ($lists as $list)
                                    <option value="{{ $list->id }}" @selected(old('list_id', $activeList?->id) == $list->id)>{{ $list->name }}</option>
                                @endforeach
                            </select>
                        </label>
                        <p class="text-xs text-ink-400">Next you'll match your columns (name, email, company…) and see a preview before anything is saved.</p>
                        <x-ui.button variant="brand" icon="arrow-right" class="w-full">Upload &amp; preview</x-ui.button>
                    </form>
                </details>
            </div>

            {{-- Filters --}}
            <form method="GET" action="{{ route('crm.index') }}" class="flex flex-wrap items-center gap-2 rounded-2xl border border-line bg-surface p-3" role="search">
                @if ($activeList)
                    <input type="hidden" name="list" value="{{ $activeList->id }}">
                @endif
                <label class="relative flex-1 min-w-[200px]">
                    <span class="sr-only">Search contacts</span>
                    <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" />
                    <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, company, or email" class="w-full rounded-lg border border-line py-2 pl-9 pr-3 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                </label>
                <label>
                    <span class="sr-only">Status</span>
                    <select name="status" class="rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">All statuses</option>
                        @foreach (['new', 'contacted', 'qualified', 'customer', 'unqualified', 'replied'] as $status)
                            <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
                        @endforeach
                    </select>
                </label>
                <label>
                    <span class="sr-only">Source</span>
                    <select name="source" class="rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source }}" @selected(request('source') === $source)>{{ ucwords(str_replace('_', ' ', $source)) }}</option>
                        @endforeach
                    </select>
                </label>
                <x-ui.button variant="secondary" icon="funnel">Filter</x-ui.button>
                @if (request('q') || request('status') || request('source'))
                    <a href="{{ route('crm.index', $activeList ? ['list' => $activeList->id] : []) }}" class="text-sm text-ink-600 hover:text-ink-900 px-2">Clear</a>
                @endif
                <a href="{{ route('crm.export', $activeList ? ['list' => $activeList->id] : []) }}" class="ml-auto inline-flex items-center gap-1.5 text-sm text-brand-600 hover:text-brand-700 px-2">
                    <x-heroicon-m-arrow-down-tray class="h-4 w-4" /> Export CSV
                </a>
            </form>

            @if ($activeList)
                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-line bg-gradient-to-r from-brand-50 to-white px-4 py-3">
                    <p class="text-sm text-ink-900">
                        Showing <span class="font-semibold">{{ $activeList->name }}</span> · {{ number_format($activeList->contacts_count) }} contact{{ $activeList->contacts_count === 1 ? '' : 's' }}
                    </p>
                    <div class="flex items-center gap-2">
                        <x-ui.button variant="secondary" size="sm" icon="queue-list" :href="route('crm.lists.show', $activeList)">Open list</x-ui.button>
                        <x-ui.button variant="primary" size="sm" icon="sparkles" :href="route('crm.campaigns.create', ['list' => $activeList->id])">Create Campaign</x-ui.button>
                    </div>
                </div>
            @endif

            @include('dashboard.crm.partials.contacts-table', ['contacts' => $contacts, 'lists' => $lists, 'filtered' => request('q') || request('status') || request('source') || $activeList])
        </div>
    </div>
@endsection
