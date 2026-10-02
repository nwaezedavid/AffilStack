<div data-follow-up class="rounded-xl border border-line bg-surface-muted/40 p-4">
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3">
        <label class="flex items-center gap-2 text-sm text-ink-900">
            <x-heroicon-m-clock class="h-4 w-4 text-ink-400" />
            Send
            <input type="number" name="follow_ups[{{ $i }}][delay_days]" value="{{ $step['delay_days'] ?? 3 }}" min="1" max="30" class="w-16 rounded-lg border border-line bg-white px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500" aria-label="Days to wait">
            days later if no reply
        </label>
        <button type="button" data-remove-follow-up class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs text-red-600 hover:bg-red-50"><x-heroicon-m-trash class="h-3.5 w-3.5" /> Remove</button>
    </div>
    <label class="block mb-2">
        <span class="sr-only">Follow-up subject</span>
        <input name="follow_ups[{{ $i }}][subject]" value="{{ $step['subject'] ?? '' }}" maxlength="200" placeholder="Subject — leave empty to reply in the same thread (Re: …)" class="merge-target w-full rounded-lg border border-line bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
    </label>
    <label class="block">
        <span class="sr-only">Follow-up message</span>
        <textarea name="follow_ups[{{ $i }}][body]" rows="5" maxlength="5000" class="merge-target w-full rounded-lg border border-line bg-white px-3 py-2 font-mono text-[13px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-brand-500">{{ $step['body'] ?? '' }}</textarea>
    </label>
</div>
