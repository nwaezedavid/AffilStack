<div class="rounded-3xl border border-line bg-white p-5 sm:p-6">
    <h3 class="font-display text-lg font-semibold text-navy-900">Who should we write to?</h3>
    @if ($contacts->isEmpty())
        <div class="mt-3 rounded-2xl bg-emerald-50/60 p-4 ring-1 ring-emerald-100">
            <p class="text-sm text-ink-900">You don't have any CRM contacts yet. Save prospects from Google Maps or LinkedIn with the Chrome extension, or add one by hand — then come back here.</p>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-ui.button :href="route('offers.feature', [$offer, 'maps'])" variant="primary" size="sm" icon="map-pin">Find local prospects</x-ui.button>
                <x-ui.button :href="route('crm.index')" variant="secondary" size="sm" icon="user-plus">Add a contact</x-ui.button>
            </div>
        </div>
    @else
        <p class="mt-1 text-sm text-ink-600">Pick one prospect — the 5 emails are written for them specifically, not a generic template.</p>
        <form method="POST" action="{{ route('offers.nurture.generate', $offer) }}" class="mt-4 flex flex-col gap-3 sm:flex-row">
            @csrf
            <label for="contact_id" class="sr-only">Contact</label>
            <select id="contact_id" name="contact_id" required class="flex-1 rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="">Choose a contact…</option>
                @foreach ($contacts as $contact)
                    <option value="{{ $contact->id }}">{{ $contact->name ?: $contact->email ?: 'Contact #'.$contact->id }}{{ $contact->company ? ' · '.$contact->company : '' }}</option>
                @endforeach
            </select>
            <x-ui.button variant="primary" icon="sparkles" size="lg">Write sequence ({{ config('credits.costs.email_nurture') }} credits)</x-ui.button>
        </form>
        <p class="mt-3 text-xs text-ink-400">Sending uses your connected inbox — <a href="{{ route('email-connections.index') }}" class="font-medium text-brand-600 hover:text-brand-700">check email sending</a>. Want to email a whole list at once? Use <strong>Create Campaign</strong> on the Local leads page.</p>
    @endif
</div>
