@php
    $emails = $swipes->get('email', collect());
    $mergeTags = [
        '{first_name}' => 'Keep {first_name}',
        '*|FNAME|*' => 'Mailchimp: *|FNAME|*',
        '{'.'{ subscriber.first_name }'.'}' => 'Kit (ConvertKit)',
        '%FIRSTNAME%' => 'ActiveCampaign',
        '{$name}' => 'MailerLite',
        '{'.'{first_name}'.'}' => 'beehiiv / Systeme.io',
        'there' => 'No tag ("Hi there")',
    ];
@endphp

<div class="mb-6 flex flex-col gap-4 rounded-2xl border border-line bg-surface p-5 sm:flex-row sm:items-end sm:justify-between sm:p-6">
    <div>
        <h3 class="font-display font-semibold text-base text-navy-900">Ready-to-send emails</h3>
        <p class="mt-1 max-w-2xl text-sm text-ink-600">Each one is written for a different reader. Your tracked link and your name are already filled in. Pick your email tool so the reader's first name is filled in too, then copy and send.</p>
    </div>
    <div class="shrink-0">
        <label for="merge-tag" class="block text-xs font-medium text-ink-900 mb-1">Your email tool's first-name tag</label>
        <select id="merge-tag" class="w-full sm:w-60 rounded-xl border border-line bg-white px-3 py-2 text-sm text-navy-900 focus:outline-none focus:ring-2 focus:ring-brand-500">
            @foreach ($mergeTags as $value => $label)
                <option value="{{ $value }}">{{ $label }}</option>
            @endforeach
        </select>
    </div>
</div>

@if ($emails->isEmpty())
    <x-ui.empty-state icon="envelope" title="No email swipes yet">The AffilStack team hasn't published any email swipes yet. Check back soon.</x-ui.empty-state>
@else
    <div class="grid gap-5 xl:grid-cols-2">
        @foreach ($emails as $email)
            <article class="flex flex-col rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <header class="flex items-start justify-between gap-3 border-b border-line px-5 py-4">
                    <div class="flex items-start gap-3 min-w-0">
                        <span class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-50 text-brand-600"><x-heroicon-o-envelope class="h-5 w-5" /></span>
                        <div class="min-w-0">
                            <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-gold-600">Email {{ $loop->iteration }}</p>
                            <h4 class="font-display text-sm font-semibold text-navy-900">{{ $email['title'] }}</h4>
                        </div>
                    </div>
                    <x-ui.copy-button :target="'swipe-all-'.$email['id']" label="Copy all" variant="primary" size="xs" class="shrink-0" />
                </header>
                <div class="flex-1 px-5 py-4">
                    <div class="flex items-center justify-between gap-3 rounded-xl bg-surface-muted px-3 py-2">
                        <p class="min-w-0 text-sm"><span class="text-ink-400">Subject:</span> <span id="swipe-subject-{{ $email['id'] }}" class="font-medium text-navy-900">{{ $email['subject'] }}</span></p>
                        <x-ui.copy-button :target="'swipe-subject-'.$email['id']" label="Copy" size="xs" class="shrink-0" />
                    </div>
                    <div class="relative mt-3">
                        <pre id="swipe-body-{{ $email['id'] }}" data-merge-body class="max-h-72 overflow-y-auto whitespace-pre-wrap break-words rounded-xl border border-line bg-white p-4 font-sans text-sm leading-relaxed text-ink-900" data-raw="{{ $email['body'] }}">{{ $email['body'] }}</pre>
                    </div>
                    <pre id="swipe-all-{{ $email['id'] }}" data-merge-body class="hidden" data-raw="{{ 'Subject: '.$email['subject']."\n\n".$email['body'] }}">{{ 'Subject: '.$email['subject']."\n\n".$email['body'] }}</pre>
                </div>
                <footer class="flex items-center justify-between gap-3 border-t border-line px-5 py-3">
                    <p class="text-xs text-ink-400">Includes an affiliate disclosure. Please keep it.</p>
                    <x-ui.copy-button :target="'swipe-body-'.$email['id']" label="Copy body" size="xs" />
                </footer>
            </article>
        @endforeach
    </div>
@endif

<x-ui.next-step class="mt-6" :href="$kitTabUrl('social')" action="Social posts" data-tab-link="social">
    Sent an email? Post about AffilStack on your socials the same week. People often need to see a recommendation twice before they click.
</x-ui.next-step>

@push('scripts')
    <script>
        (function () {
            var select = document.getElementById('merge-tag');
            if (!select) { return; }
            function apply() {
                var tag = select.value;
                document.querySelectorAll('[data-merge-body]').forEach(function (node) {
                    node.textContent = node.getAttribute('data-raw').split('{first_name}').join(tag);
                });
            }
            select.addEventListener('change', apply);
        })();
    </script>
@endpush
