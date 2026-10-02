@extends('layouts.app')

@section('title', 'Edit campaign')

@section('content')
    @php
        $followUps = old('follow_ups', $campaign->follow_up_steps ?? []);
        $connected = $connection && $connection->isVerified();
        $senderName = $connection?->credential('from_name') ?: auth()->user()->name;
    @endphp

    <nav class="mb-3 text-xs text-ink-600" aria-label="Breadcrumb">
        <a href="{{ route('crm.index') }}" class="hover:text-brand-600">CRM</a>
        <span class="mx-1 text-ink-400">/</span>
        <a href="{{ route('crm.lists.show', $campaign->list) }}" class="hover:text-brand-600">{{ $campaign->list->name }}</a>
        <span class="mx-1 text-ink-400">/</span>
        <span class="text-ink-900">Draft</span>
    </nav>

    <x-ui.page-header :title="$campaign->name" eyebrow="Step 2 of 2 · Review, edit & approve" icon="pencil-square" tone="violet"
        :subtitle="'Going to '.number_format($withEmail).' of '.number_format($audience).' contacts on '.$campaign->list->name.' who have an email address. Edit anything — the preview updates as you type.'" />

    @if ($unknownFields)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Unknown merge field{{ count($unknownFields) > 1 ? 's' : '' }}: <strong>{{ collect($unknownFields)->map(fn ($f) => '{'.$f.'}')->implode(', ') }}</strong> — they'd be sent as typed. Use the field buttons below.
        </div>
    @endif

    <form method="POST" action="{{ route('crm.campaigns.update', $campaign) }}" id="campaign-form" class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)]">
        @csrf @method('PUT')

        <div class="min-w-0 space-y-5">
            <x-ui.card title="Email" icon="envelope">
                <div class="space-y-4">
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Campaign name <span class="text-ink-400">(only you see this)</span></span>
                        <input name="name" value="{{ old('name', $campaign->name) }}" required maxlength="150" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Subject line</span>
                        <input name="subject" data-preview="subject" value="{{ old('subject', $campaign->subject) }}" required maxlength="200" class="merge-target w-full rounded-lg border border-line px-3 py-2 text-sm font-medium focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </label>
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1">
                            <label for="body" class="text-xs font-medium text-ink-600">Message</label>
                            <span class="text-[11px] text-ink-400" id="word-count"></span>
                        </div>
                        <textarea id="body" name="body" data-preview="body" rows="14" required maxlength="10000" class="merge-target w-full rounded-lg border border-line px-3 py-2.5 font-mono text-[13px] leading-relaxed focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('body', $campaign->body) }}</textarea>
                        <div class="mt-2">
                            <p class="text-[11px] font-mono uppercase tracking-[0.12em] text-ink-400 mb-1.5">Insert a merge field</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($fields as $field => $description)
                                    <button type="button" data-insert="{{ '{'.$field.'}' }}" title="{{ $description }}" class="inline-flex items-center gap-1 rounded-full bg-brand-50 px-2.5 py-1 font-mono text-[11px] text-brand-700 ring-1 ring-inset ring-brand-600/20 transition hover:bg-brand-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                                        {{ '{'.$field.'}' }}
                                    </button>
                                @endforeach
                            </div>
                            <p class="mt-2 text-xs text-ink-400">{affiliate_link} becomes your cloaked, click-tracked link (with the affiliate disclosure added automatically). Your name and an unsubscribe footer are added below every email.</p>
                        </div>
                    </div>
                </div>
            </x-ui.card>

            <x-ui.card title="Follow-ups" icon="arrow-path" description="Sent only to contacts who haven't replied — they stop the moment someone answers.">
                <div id="follow-ups" class="space-y-4">
                    @foreach ($followUps as $i => $step)
                        @include('dashboard.crm.campaigns.partials.follow-up', ['i' => $i, 'step' => $step])
                    @endforeach
                </div>
                <button type="button" id="add-follow-up" class="mt-4 inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    <x-heroicon-m-plus class="h-4 w-4" /> Add a follow-up
                </button>
                <template id="follow-up-template">
                    @include('dashboard.crm.campaigns.partials.follow-up', ['i' => '__INDEX__', 'step' => ['delay_days' => 3, 'subject' => '', 'body' => '']])
                </template>
            </x-ui.card>

            <x-ui.card title="Automation" icon="bolt">
                <div class="space-y-3">
                    <label class="flex items-start gap-3 rounded-xl border border-line p-4 cursor-pointer hover:bg-surface-muted/50">
                        <input type="hidden" name="autopilot" value="0">
                        <input type="checkbox" name="autopilot" value="1" @checked(old('autopilot', $campaign->autopilot)) class="mt-0.5 h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500">
                        <span class="text-sm">
                            <span class="font-medium text-ink-900 flex items-center gap-1.5"><x-heroicon-m-bolt class="h-4 w-4 text-gold-500" /> Autopilot</span>
                            <span class="block text-xs text-ink-600">Every contact added to <strong>{{ $campaign->list->name }}</strong> later — from Local Leads, the Chrome extension, an import or by hand — gets this campaign automatically.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-xl border border-line p-4 cursor-pointer hover:bg-surface-muted/50">
                        <input type="hidden" name="personalize_opening" value="0">
                        <input type="checkbox" id="personalize" name="personalize_opening" value="1" @checked(old('personalize_opening', $campaign->personalize_opening)) class="mt-0.5 h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500">
                        <span class="text-sm">
                            <span class="font-medium text-ink-900 flex items-center gap-1.5"><x-heroicon-m-sparkles class="h-4 w-4 text-violet-500" /> AI personal first line</span>
                            <span class="block text-xs text-ink-600">A unique opening sentence for each contact, written as their email sends. {{ $openingCost }} credit{{ $openingCost === 1 ? '' : 's' }} per contact.</span>
                        </span>
                    </label>
                </div>
            </x-ui.card>
        </div>

        {{-- Live preview + actions --}}
        <aside class="space-y-4 xl:sticky xl:top-6 self-start min-w-0">
            <div class="rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)] overflow-hidden">
                <div class="flex items-center justify-between gap-2 border-b border-line bg-surface-muted/70 px-4 py-2.5">
                    <span class="flex items-center gap-1.5 text-xs font-medium text-ink-600"><x-heroicon-m-eye class="h-4 w-4" /> Live preview</span>
                    <label class="min-w-0">
                        <span class="sr-only">Preview for contact</span>
                        <select id="preview-contact" class="max-w-[13rem] truncate rounded-lg border border-line bg-white px-2 py-1 text-xs focus:outline-none focus:ring-2 focus:ring-brand-500">
                            @foreach ($samples as $i => $sample)
                                <option value="{{ $i }}">{{ $sample['label'] }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
                <div class="px-4 py-3 text-xs border-b border-line space-y-1">
                    <p><span class="inline-block w-12 text-ink-400">From</span> <span class="text-ink-900">{{ $senderName }}</span> <span class="text-ink-400">&lt;{{ $connection?->connected_email ?? 'not connected' }}&gt;</span></p>
                    <p><span class="inline-block w-12 text-ink-400">To</span> <span id="preview-to" class="text-ink-900"></span></p>
                    <p class="pt-1"><span id="preview-subject" class="font-display font-semibold text-sm text-navy-900"></span></p>
                </div>
                <div class="px-4 py-4">
                    <div id="preview-body" class="whitespace-pre-line break-words text-sm leading-relaxed text-ink-900"></div>
                    <p class="mt-1 text-sm text-ink-900">{{ $senderName }}</p>
                    <p class="mt-6 border-t border-line pt-3 text-[11px] leading-relaxed text-ink-400">
                        {{ $connection?->footer_business_name ?: (auth()->user()->company_name ?: auth()->user()->name) }}
                        @if ($connection?->footer_address) · {{ $connection->footer_address }} @endif
                        <br>Not interested? <span class="underline">Unsubscribe</span> and you won't hear from us again.
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-line bg-surface p-4 space-y-3">
                @if (! $connected)
                    <div class="flex items-start gap-2 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                        <x-heroicon-m-exclamation-triangle class="h-4 w-4 shrink-0" />
                        <span>Connect and verify your email before approving. <a href="{{ route('email-connections.index') }}" class="font-medium underline">Email Sending</a></span>
                    </div>
                @else
                    <p class="flex items-center gap-2 text-xs text-ink-600">
                        <x-ui.status-pill status="verified" label="Sending ready" />
                        {{ number_format($remainingToday) }} of {{ number_format($dailyCap) }} sends left today
                    </p>
                @endif
                <button type="submit" name="intent" value="approve" @disabled(! $connected || $withEmail === 0)
                    onclick="return confirm('Send this campaign to {{ $withEmail }} contact(s) now? Emails go out in paced batches.')"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                    <x-heroicon-m-paper-airplane class="h-4 w-4" /> Approve and Send to {{ number_format($withEmail) }}
                </button>
                <button type="submit" name="intent" value="save" class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-white px-5 py-2.5 text-sm font-medium text-navy-900 ring-1 ring-line transition hover:bg-surface-muted focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                    Save draft
                </button>
                @if ($withEmail < $audience)
                    <p class="text-xs text-ink-400">{{ number_format($audience - $withEmail) }} contact(s) without an email (or unsubscribed) will be skipped.</p>
                @endif
            </div>
        </aside>
    </form>

    <form method="POST" action="{{ route('crm.campaigns.destroy', $campaign) }}" class="mt-6" onsubmit="return confirm('Delete this draft?')">
        @csrf @method('DELETE')
        <button class="text-xs font-medium text-red-600 hover:text-red-700">Delete draft</button>
    </form>
@endsection

@push('scripts')
    <script>
        (function () {
            const samples = @json($samples);
            const subject = document.querySelector('[data-preview=subject]');
            const body = document.getElementById('body');
            const picker = document.getElementById('preview-contact');
            const personalize = document.getElementById('personalize');
            const wordCount = document.getElementById('word-count');
            const followUps = document.getElementById('follow-ups');
            const template = document.getElementById('follow-up-template');
            let lastFocused = body;

            function fill(text, sample, withOpening) {
                const values = Object.assign({}, sample.values);
                if (!withOpening) { values.opening_line = ''; }
                if (withOpening && values.opening_line && text.indexOf('{opening_line}') === -1) {
                    const lines = text.split('\n');
                    text = lines.length > 1 ? lines[0] + '\n\n{opening_line}\n' + lines.slice(1).join('\n') : '{opening_line}\n\n' + text;
                }
                if (!values.opening_line) { text = text.replace(/^[ \t]*\{opening_line\}[ \t]*\n\n?/m, ''); }
                return text.replace(/\{([a-z_]+)\}/g, function (m, key) {
                    return Object.prototype.hasOwnProperty.call(values, key) ? values[key] : m;
                }).replace(/\n{3,}/g, '\n\n').trim();
            }

            function render() {
                const sample = samples[picker.value] || samples[0];
                document.getElementById('preview-to').textContent = sample.email || '(no email — will be skipped)';
                document.getElementById('preview-subject').textContent = fill(subject.value, sample, false);
                document.getElementById('preview-body').textContent = fill(body.value, sample, personalize.checked);
                const words = body.value.trim().split(/\s+/).filter(Boolean).length;
                wordCount.textContent = words + ' words' + (words > 160 ? ' · shorter emails get more replies' : '');
            }

            document.addEventListener('focusin', function (e) {
                if (e.target.classList && e.target.classList.contains('merge-target')) { lastFocused = e.target; }
            });

            document.querySelectorAll('[data-insert]').forEach(function (button) {
                button.addEventListener('click', function () {
                    const field = lastFocused || body;
                    const start = field.selectionStart ?? field.value.length;
                    const end = field.selectionEnd ?? field.value.length;
                    field.value = field.value.slice(0, start) + button.dataset.insert + field.value.slice(end);
                    field.focus();
                    field.selectionStart = field.selectionEnd = start + button.dataset.insert.length;
                    render();
                });
            });

            document.getElementById('add-follow-up').addEventListener('click', function () {
                if (followUps.children.length >= 3) { alert('Up to 3 follow-ups.'); return; }
                const index = Date.now();
                followUps.insertAdjacentHTML('beforeend', template.innerHTML.replaceAll('__INDEX__', index));
            });

            followUps.addEventListener('click', function (e) {
                const remove = e.target.closest('[data-remove-follow-up]');
                if (remove) { remove.closest('[data-follow-up]').remove(); }
            });

            [subject, body, picker, personalize].forEach(function (el) {
                el.addEventListener('input', render);
                el.addEventListener('change', render);
            });
            render();
        })();
    </script>
@endpush
