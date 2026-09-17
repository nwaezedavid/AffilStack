@extends('layouts.app')

@section('title', 'CRM Contacts')

@section('content')
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        <div class="bg-surface border border-line rounded-lg p-4">
            <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">Total contacts</div>
            <div class="font-display font-semibold text-2xl text-navy-900">{{ number_format($stats['total']) }}</div>
        </div>
        @foreach ($stats['by_status'] as $status => $count)
            <div class="bg-surface border border-line rounded-lg p-4">
                <div class="text-xs uppercase tracking-wide text-ink-400 font-mono mb-1">{{ ucfirst($status) }}</div>
                <div class="font-display font-semibold text-2xl text-navy-900">{{ number_format($count) }}</div>
            </div>
        @endforeach
    </div>

    <div class="flex flex-wrap items-center gap-3 mb-6 text-sm text-ink-600">
        <span class="px-2.5 py-1 rounded-full bg-surface-muted">{{ number_format($stats['sent_this_month']) }} email{{ $stats['sent_this_month'] === 1 ? '' : 's' }} sent this month</span>
        @if ($stats['open_rate_this_month'] !== null)
            <span class="px-2.5 py-1 rounded-full bg-surface-muted">{{ $stats['open_rate_this_month'] }}% open rate this month</span>
        @endif
    </div>

    <div class="flex items-center justify-between mb-5">
        <p class="text-sm text-ink-600 max-w-lg">Every lead you save here — manually, or via <a href="{{ route('leads.index') }}" class="text-brand-600 hover:text-brand-700 underline">Local Leads</a> from Google Maps — lives in one exportable list.</p>
        <a href="{{ route('crm.export') }}" class="text-sm rounded-md border border-line px-3 py-1.5 hover:bg-surface-muted transition whitespace-nowrap">Export CSV</a>
    </div>

    <details class="bg-surface border border-line rounded-lg p-5 mb-6">
        <summary class="cursor-pointer text-sm font-medium text-ink-900">+ Add a contact manually</summary>
        <form method="POST" action="{{ route('crm.store') }}" class="grid sm:grid-cols-2 gap-3 mt-4">
            @csrf
            <input name="name" placeholder="Name" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="company" placeholder="Company" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="title" placeholder="Title" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="email" type="email" placeholder="Email" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="phone" placeholder="Phone" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="website" type="url" placeholder="Website" class="rounded-md border border-line px-3 py-2 text-sm">
            <input name="location" placeholder="Location" class="rounded-md border border-line px-3 py-2 text-sm sm:col-span-2">
            <textarea name="notes" placeholder="Notes" rows="2" class="rounded-md border border-line px-3 py-2 text-sm sm:col-span-2"></textarea>
            <button class="sm:col-span-2 rounded-md bg-navy-900 text-white text-sm py-2 hover:bg-navy-800 transition">Save contact</button>
        </form>
    </details>

    <form method="GET" action="{{ route('crm.index') }}" class="flex flex-wrap items-center gap-2 mb-4">
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Search by name, company, or email"
               class="flex-1 min-w-[220px] rounded-md border border-line px-3 py-2 text-sm">
        <select name="status" class="rounded-md border border-line px-3 py-2 text-sm">
            <option value="">All statuses</option>
            @foreach (['new', 'contacted', 'qualified', 'customer', 'unqualified'] as $status)
                <option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>
            @endforeach
        </select>
        <button class="text-sm rounded-md border border-line text-ink-900 px-3 py-2 hover:bg-surface-muted transition">Filter</button>
        @if (request('q') || request('status'))
            <a href="{{ route('crm.index') }}" class="text-sm text-ink-500 hover:text-ink-700">Clear</a>
        @endif
    </form>

    @if ($contacts->isEmpty())
        <div class="bg-surface border border-dashed border-line rounded-lg p-8 text-center text-sm text-ink-600">
            @if (request('q') || request('status'))
                No contacts match your search.
            @else
                No contacts saved yet.
            @endif
        </div>
    @else
        <div class="bg-surface border border-line rounded-lg overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2.5">Name</th>
                        <th class="text-left px-4 py-2.5">Company</th>
                        <th class="text-left px-4 py-2.5">Contact</th>
                        <th class="text-left px-4 py-2.5">Source</th>
                        <th class="text-left px-4 py-2.5">Status</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($contacts as $contact)
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-ink-900">{{ $contact->name ?: '—' }}</div>
                                <div class="text-xs text-ink-600">{{ $contact->title }}</div>
                            </td>
                            <td class="px-4 py-3 text-ink-900">{{ $contact->company ?: '—' }}</td>
                            <td class="px-4 py-3 text-ink-600 text-xs">
                                {{ $contact->email }}<br>{{ $contact->phone }}
                            </td>
                            <td class="px-4 py-3 text-ink-600 capitalize">{{ str_replace('_', ' ', $contact->source) }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('crm.update', $contact) }}">
                                    @csrf @method('PATCH')
                                    <select name="status" onchange="this.form.submit()" class="text-xs rounded border border-line px-2 py-1">
                                        @foreach (['new', 'contacted', 'qualified', 'customer', 'unqualified'] as $status)
                                            <option value="{{ $status }}" @selected($contact->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <form method="POST" action="{{ route('crm.destroy', $contact) }}" onsubmit="return confirm('Remove this contact?')">
                                    @csrf @method('DELETE')
                                    <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-4">{{ $contacts->links() }}</div>
    @endif
@endsection
