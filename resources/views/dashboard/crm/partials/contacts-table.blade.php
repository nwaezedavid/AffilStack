{{-- The contacts table with bulk actions (add to / remove from a list,
     delete, export). Checkboxes belong to #bulk-form through the form
     attribute, so each row's own status/edit/delete forms stay separate. --}}
@php($statuses = ['new', 'contacted', 'qualified', 'customer', 'unqualified'])
@if ($contacts->isEmpty())
    <x-ui.empty-state icon="user-group" :title="$filtered ? 'No contacts match your search.' : 'No contacts saved yet'"
        :href="$filtered ? null : route('leads.index')" :action="$filtered ? null : 'Find local leads'">
        @if ($filtered)
            No contacts match your search — try another list or clear the filters.
        @else
            Add contacts by hand, import a CSV, find businesses on Google Maps with Local Leads, or save them from the Chrome extension.
        @endif
    </x-ui.empty-state>
@else
    <form id="bulk-form" method="POST" action="{{ route('crm.contacts.bulk') }}">@csrf</form>

    <div class="rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)] overflow-hidden">
        <div id="bulk-bar" class="flex flex-wrap items-center gap-2 border-b border-line bg-surface-muted/70 px-4 py-2.5 text-sm">
            <span class="text-ink-600"><span id="bulk-count" class="font-semibold text-ink-900">0</span> selected</span>
            <label class="sr-only" for="bulk-action">Bulk action</label>
            <select id="bulk-action" name="action" form="bulk-form" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="add_to_list">Add to list…</option>
                <option value="remove_from_list">Remove from list…</option>
                <option value="export">Export selected</option>
                <option value="delete">Delete selected</option>
            </select>
            <label class="sr-only" for="bulk-list">List</label>
            <select id="bulk-list" name="list_id" form="bulk-form" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach ($lists as $list)
                    <option value="{{ $list->id }}">{{ $list->name }}</option>
                @endforeach
            </select>
            <button type="submit" form="bulk-form" id="bulk-apply" disabled class="inline-flex items-center gap-1.5 rounded-full bg-navy-900 px-3.5 py-1.5 text-xs font-medium text-white transition hover:bg-navy-800 disabled:cursor-not-allowed disabled:opacity-40 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2">Apply</button>
        </div>

        <div class="relative overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-[11px] font-mono uppercase tracking-[0.12em] text-ink-400">
                    <tr class="border-b border-line">
                        <th class="w-10 px-4 py-3"><input type="checkbox" id="bulk-all" class="h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500" aria-label="Select all contacts on this page"></th>
                        <th class="px-3 py-3 font-medium">Contact</th>
                        <th class="px-3 py-3 font-medium">Email / phone</th>
                        <th class="px-3 py-3 font-medium">Lists</th>
                        <th class="px-3 py-3 font-medium">Status</th>
                        <th class="px-3 py-3"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($contacts as $contact)
                        <tr class="align-top hover:bg-surface-muted/50 transition">
                            <td class="px-4 py-3"><input type="checkbox" name="contact_ids[]" value="{{ $contact->id }}" form="bulk-form" class="bulk-item h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500" aria-label="Select {{ $contact->name ?: $contact->company }}"></td>
                            <td class="px-3 py-3">
                                <div class="flex items-center gap-3">
                                    <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-navy-800 text-xs font-semibold text-white" aria-hidden="true">{{ mb_strtoupper(mb_substr($contact->name ?: ($contact->company ?: '?'), 0, 1)) }}</span>
                                    <div class="min-w-0">
                                        <div class="font-medium text-ink-900">{{ $contact->name ?: '—' }}</div>
                                        <div class="text-xs text-ink-600 truncate max-w-[13rem]">{{ collect([$contact->title, $contact->company !== $contact->name ? $contact->company : null])->filter()->implode(' · ') ?: $contact->location }}</div>
                                        <div class="mt-0.5 text-[11px] text-ink-400 capitalize">via {{ str_replace('_', ' ', $contact->source) }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-3 text-xs">
                                @if ($contact->email)
                                    <div class="text-ink-900">{{ $contact->email }}</div>
                                @else
                                    <div class="inline-flex items-center gap-1 rounded-full bg-amber-50 px-2 py-0.5 text-amber-700"><x-heroicon-m-exclamation-triangle class="h-3 w-3" /> No email</div>
                                @endif
                                <div class="text-ink-400 mt-0.5">{{ $contact->phone }}</div>
                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($contact->replied_at)
                                        <a href="{{ route('crm.inbox.show', $contact) }}" class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-700 hover:bg-emerald-100"><x-heroicon-m-chat-bubble-left-right class="h-3 w-3" /> Replied</a>
                                    @endif
                                    @if ($contact->unsubscribed_at)
                                        <span class="inline-flex items-center rounded-full bg-rose-50 px-2 py-0.5 text-[11px] font-medium text-rose-700">Unsubscribed</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <div class="flex flex-col items-start gap-1">
                                    @forelse ($contact->lists as $list)
                                        <a href="{{ route('crm.lists.show', $list) }}" title="{{ $list->name }}" class="inline-block max-w-[11rem] truncate whitespace-nowrap rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $list->chipClasses() }}">{{ $list->name }}</a>
                                    @empty
                                        <span class="text-xs text-ink-400">—</span>
                                    @endforelse
                                </div>
                            </td>
                            <td class="px-3 py-3">
                                <form method="POST" action="{{ route('crm.update', $contact) }}">
                                    @csrf @method('PATCH')
                                    <label class="sr-only" for="status-{{ $contact->id }}">Pipeline status</label>
                                    <select id="status-{{ $contact->id }}" name="status" onchange="this.form.submit()" class="rounded-lg border border-line bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                                        @foreach ($statuses as $status)
                                            <option value="{{ $status }}" @selected($contact->status === $status)>{{ ucfirst($status) }}</option>
                                        @endforeach
                                    </select>
                                </form>
                            </td>
                            <td class="px-3 py-3 text-right whitespace-nowrap">
                                <details class="relative inline-block text-left">
                                    <summary class="inline-flex cursor-pointer list-none items-center rounded-lg p-1.5 text-ink-400 hover:bg-surface-muted hover:text-ink-900" aria-label="Edit {{ $contact->name ?: $contact->company }}">
                                        <x-heroicon-m-pencil-square class="h-4 w-4" />
                                    </summary>
                                    <div class="absolute right-0 z-10 mt-1 w-72 rounded-xl border border-line bg-white p-3 shadow-lg">
                                        <form method="POST" action="{{ route('crm.update', $contact) }}" class="space-y-2">
                                            @csrf @method('PATCH')
                                            @foreach ([['name', 'Name', 'text'], ['company', 'Company', 'text'], ['email', 'Email', 'email'], ['phone', 'Phone', 'text'], ['website', 'Website', 'url'], ['location', 'Location', 'text']] as [$field, $label, $type])
                                                <label class="block text-left">
                                                    <span class="block text-[11px] font-medium text-ink-600">{{ $label }}</span>
                                                    <input name="{{ $field }}" type="{{ $type }}" value="{{ $contact->{$field} }}" class="w-full rounded-lg border border-line px-2.5 py-1.5 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                                                </label>
                                            @endforeach
                                            <x-ui.button size="sm" class="w-full">Save</x-ui.button>
                                        </form>
                                        <form method="POST" action="{{ route('crm.destroy', $contact) }}" onsubmit="return confirm('Delete this contact and its email history?')" class="mt-2">
                                            @csrf @method('DELETE')
                                            <button class="w-full rounded-full px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Remove contact</button>
                                        </form>
                                    </div>
                                </details>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    <div class="mt-4">{{ $contacts->links() }}</div>

    @push('scripts')
        <script>
            (function () {
                const all = document.getElementById('bulk-all');
                const items = Array.from(document.querySelectorAll('.bulk-item'));
                const count = document.getElementById('bulk-count');
                const apply = document.getElementById('bulk-apply');
                const action = document.getElementById('bulk-action');
                const list = document.getElementById('bulk-list');
                const form = document.getElementById('bulk-form');

                function refresh() {
                    const n = items.filter(function (i) { return i.checked; }).length;
                    count.textContent = n;
                    apply.disabled = n === 0;
                    all.checked = n > 0 && n === items.length;
                    all.indeterminate = n > 0 && n < items.length;
                    list.hidden = !['add_to_list', 'remove_from_list'].includes(action.value);
                }

                all.addEventListener('change', function () {
                    items.forEach(function (i) { i.checked = all.checked; });
                    refresh();
                });
                items.forEach(function (i) { i.addEventListener('change', refresh); });
                action.addEventListener('change', refresh);
                form.addEventListener('submit', function (e) {
                    if (action.value === 'delete' && !confirm('Delete the selected contacts? This cannot be undone.')) {
                        e.preventDefault();
                    }
                });
                refresh();
            })();
        </script>
    @endpush
@endif
