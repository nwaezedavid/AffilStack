@extends('layouts.app')

@section('title', 'Email Sending')

@section('content')
    @php
        $smtp = $connection?->isSmtp() ? $connection : null;
        $sendingStatus = $connection ? $connection->sendingPillStatus() : 'not_connected';
        $imapStatus = $connection ? $connection->imapPillStatus() : 'not_connected';
        $replyMethod = $connection?->replyDetectionMethod();
        $usedPct = $dailyCap > 0 ? min(100, round($sentToday / $dailyCap * 100)) : 0;
    @endphp

    <x-ui.page-header title="Email Sending" eyebrow="Connections" icon="envelope"
        subtitle="Send your CRM campaigns from your own Gmail or business email, and let AffilStack spot replies so you can follow up and close the sale.">
        <x-slot:actions>
            @if ($connection)
                <form method="POST" action="{{ route('email-connections.test') }}" class="flex items-center gap-2">
                    @csrf
                    <x-ui.button variant="gold" icon="signal">Test connection</x-ui.button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.page-header>

    {{-- Status overview --}}
    <div class="mb-6 grid gap-3 sm:grid-cols-3">
        @foreach ([
            ['Sending', $sendingStatus, $connection ? $connection->connected_email : 'Connect Gmail or SMTP', 'paper-airplane'],
            ['Reply detection', $replyMethod ? 'verified' : $imapStatus, $replyMethod === 'gmail' ? 'Gmail inbox' : ($connection?->hasImap() ? $connection->imapCredential('username') : 'Add your IMAP mailbox'), 'inbox-arrow-down'],
            ['Today\'s sending', $connection ? 'connected' : 'not_connected', number_format($sentToday).' of '.number_format($dailyCap).' emails', 'chart-bar'],
        ] as [$label, $status, $detail, $icon])
            <div class="flex items-center gap-3 rounded-2xl border border-line bg-surface p-4 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                    <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-5 w-5" />
                </span>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-xs font-medium text-ink-600">{{ $label }}</p>
                        @if ($label !== 'Today\'s sending')
                            <x-ui.status-pill :status="$status" />
                        @endif
                    </div>
                    <p class="mt-0.5 truncate text-sm font-medium text-ink-900">{{ $detail }}</p>
                    @if ($label === 'Today\'s sending')
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-surface-muted" role="progressbar" aria-valuenow="{{ $usedPct }}" aria-valuemin="0" aria-valuemax="100" aria-label="Daily sending used">
                            <div class="h-full rounded-full bg-gradient-to-r from-brand-500 to-violet-500" style="width: {{ $usedPct }}%"></div>
                        </div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    {{-- 1. Sending --}}
    <section class="mb-8" aria-labelledby="sending-heading">
        <div class="mb-3 flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] font-semibold text-white">1</span>
            <h2 id="sending-heading" class="font-display text-lg font-semibold text-navy-900">Where your emails send from</h2>
        </div>

        @if ($connection)
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <x-ui.connection-card :brand="$connection->isGmail() ? 'gmail' : 'mail'"
                    :name="$connection->isGmail() ? 'Gmail' : (config('crm.presets.'.$connection->preset.'.label') ?? 'SMTP server')"
                    :account="$connection->connected_email"
                    :status="$sendingStatus"
                    :statusLabel="$sendingStatus === 'verified' ? 'Verified' : null"
                    :checkedAt="$connection->last_checked_at ?? $connection->verified_at"
                    :message="$connection->verification_message"
                    :description="$connection->isGmail() ? 'Sending through the Gmail API with your permission — no password stored.' : 'Sending through '.$connection->credential('host').':'.$connection->credential('port').' ('.strtoupper((string) ($connection->credential('encryption') ?: 'tls')).').'">
                    <form method="POST" action="{{ route('email-connections.test') }}" class="flex flex-wrap items-center gap-2">
                        @csrf
                        <label class="inline-flex items-center gap-1.5 text-xs text-ink-600">
                            <input type="checkbox" name="send_test" value="1" class="h-3.5 w-3.5 rounded border-line text-brand-600 focus:ring-brand-500"> Email me a test
                        </label>
                        <x-ui.button size="sm" variant="primary" icon="signal">Test connection</x-ui.button>
                    </form>
                    @if ($connection->isGmail() && $sendingStatus === 'failed')
                        <x-ui.oauth-button brand="google" label="Reconnect Gmail" :href="route('email-connections.gmail.redirect')" />
                    @endif
                    <form method="POST" action="{{ route('email-connections.destroy') }}" onsubmit="return confirm('Disconnect? Campaigns can\'t send until you connect an email again.')">
                        @csrf @method('DELETE')
                        <x-ui.button size="sm" variant="danger">Disconnect</x-ui.button>
                    </form>
                </x-ui.connection-card>

                <div class="rounded-2xl border border-line bg-surface p-5">
                    <h3 class="font-display font-semibold text-navy-900">Switch or update</h3>
                    <p class="mt-1 text-sm text-ink-600">Use a different account, or fix your SMTP details. Saving re-runs the live check straight away.</p>
                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($gmailAvailable)
                            <x-ui.oauth-button brand="google" :label="$connection->isGmail() ? 'Use another Gmail' : 'Connect Gmail instead'" :href="route('email-connections.gmail.redirect')" />
                        @endif
                    </div>
                    <details class="mt-4 group" @if ($errors->hasAny(['host', 'port', 'username', 'password', 'from_email'])) open @endif>
                        <summary class="inline-flex cursor-pointer list-none items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium text-brand-600 hover:bg-brand-50">
                            <x-heroicon-m-server-stack class="h-4 w-4" /> {{ $smtp ? 'Edit SMTP settings' : 'Use SMTP instead' }}
                            <x-heroicon-m-chevron-down class="h-4 w-4 transition group-open:rotate-180" />
                        </summary>
                        <div class="mt-3">
                            @include('dashboard.email-connection.partials.smtp-form', ['smtp' => $smtp])
                        </div>
                    </details>
                </div>
            </div>
        @else
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,2fr)_minmax(0,3fr)]">
                <div class="relative overflow-hidden rounded-2xl border border-line bg-gradient-to-br from-white via-white to-brand-50 p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                    <div class="pointer-events-none absolute -right-10 -top-10 h-40 w-40 rounded-full bg-gradient-to-br from-[#EA4335]/10 via-[#FBBC05]/10 to-[#34A853]/10 blur-xl" aria-hidden="true"></div>
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white shadow-sm ring-1 ring-line"><x-ui.brand-icon name="gmail" class="h-7 w-7" /></span>
                    <h3 class="mt-4 font-display text-lg font-semibold text-navy-900">Gmail</h3>
                    <p class="mt-1 text-sm text-ink-600">The quickest way: sign in with Google and approve sending. No passwords to copy.</p>
                    <ul class="mt-4 space-y-1.5 text-sm text-ink-600">
                        <li class="flex gap-2"><x-heroicon-m-check-circle class="h-4 w-4 shrink-0 text-emerald-500" /> Sends from your real Gmail address</li>
                        <li class="flex gap-2"><x-heroicon-m-check-circle class="h-4 w-4 shrink-0 text-emerald-500" /> Up to {{ $providerCaps['gmail'] }} emails a day</li>
                        <li class="flex gap-2"><x-heroicon-m-check-circle class="h-4 w-4 shrink-0 text-emerald-500" /> Revoke any time from your Google account</li>
                    </ul>
                    <div class="mt-6">
                        @if ($gmailAvailable)
                            <x-ui.oauth-button brand="google" label="Connect Gmail" :href="route('email-connections.gmail.redirect')" class="w-full sm:w-auto" />
                        @else
                            <p class="rounded-xl bg-surface-muted px-3 py-2.5 text-sm text-ink-600">Gmail connections aren't available yet — use SMTP instead (works with Gmail too, using an App Password).</p>
                        @endif
                    </div>
                </div>

                <div class="rounded-2xl border border-line bg-surface p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-navy-900 text-white"><x-heroicon-o-server-stack class="h-6 w-6" /></span>
                        <div>
                            <h3 class="font-display text-lg font-semibold text-navy-900">Business email (SMTP)</h3>
                            <p class="text-sm text-ink-600">Hostinger, Outlook, Zoho, Yahoo, Google Workspace or any mail server.</p>
                        </div>
                    </div>
                    <div class="mt-5">
                        @include('dashboard.email-connection.partials.smtp-form', ['smtp' => null])
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- 2. Reply detection --}}
    <section id="reply-detection" class="mb-8 scroll-mt-6" aria-labelledby="replies-heading">
        <div class="mb-3 flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] font-semibold text-white">2</span>
            <h2 id="replies-heading" class="font-display text-lg font-semibold text-navy-900">See when contacts reply</h2>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
            <div class="rounded-2xl border border-line bg-surface p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600"><x-heroicon-o-inbox-arrow-down class="h-6 w-6" /></span>
                        <div>
                            <h3 class="font-display text-lg font-semibold text-navy-900">Inbox reading (IMAP)</h3>
                            <p class="text-sm text-ink-600">Works with every mailbox — we only read new messages from your contacts.</p>
                        </div>
                    </div>
                    <x-ui.status-pill :status="$imapStatus" :label="$imapStatus === 'verified' ? 'Watching inbox' : null" />
                </div>

                @if ($connection?->hasImap() && $connection->imap_message)
                    <p @class([
                        'mt-4 rounded-lg px-3 py-2 text-xs',
                        'bg-emerald-50 text-emerald-700' => $imapStatus === 'verified',
                        'bg-red-50 text-red-700' => $imapStatus === 'failed',
                        'bg-surface-muted text-ink-600' => ! in_array($imapStatus, ['verified', 'failed'], true),
                    ])>{{ $connection->imap_message }}
                        @if ($connection->imap_last_polled_at) <span class="text-ink-400">· last checked {{ $connection->imap_last_polled_at->diffForHumans() }}</span> @endif
                    </p>
                @endif

                @if (! $connection)
                    <p class="mt-4 rounded-xl bg-surface-muted px-3 py-2.5 text-sm text-ink-600">Connect a sender in step 1 first.</p>
                @else
                    <form method="POST" action="{{ route('email-connections.imap.store') }}" class="mt-5 space-y-4" data-preset-form="imap">
                        @csrf
                        @include('dashboard.email-connection.partials.presets', ['kind' => 'imap', 'selected' => $connection->preset ?? 'custom'])
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_9rem]">
                            <label class="block">
                                <span class="block text-xs font-medium text-ink-600 mb-1">IMAP server</span>
                                <input name="host" data-field="host" required value="{{ old('host', $connection->imapCredential('host', config('crm.presets.'.($connection->preset ?? 'custom').'.imap.host'))) }}" placeholder="imap.yourdomain.com" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-medium text-ink-600 mb-1">Port</span>
                                <select name="port" data-field="port" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    @foreach ([993, 143] as $port)
                                        <option value="{{ $port }}" @selected((int) old('port', $connection->imapCredential('port', 993)) === $port)>{{ $port }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="block">
                                <span class="block text-xs font-medium text-ink-600 mb-1">Security</span>
                                <select name="encryption" data-field="encryption" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                                    <option value="ssl" @selected(old('encryption', $connection->imapCredential('encryption', 'ssl')) === 'ssl')>SSL/TLS</option>
                                    <option value="tls" @selected(old('encryption', $connection->imapCredential('encryption')) === 'tls')>STARTTLS</option>
                                </select>
                            </label>
                        </div>
                        @if ($smtp)
                            <label class="flex items-center gap-2 text-sm text-ink-900">
                                <input type="checkbox" name="same_as_smtp" value="1" id="same-as-smtp" @checked(old('same_as_smtp', ! $connection->hasImap())) class="h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500">
                                Use the same username and password as my SMTP sender
                            </label>
                        @endif
                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2" id="imap-login">
                            <label class="block">
                                <span class="block text-xs font-medium text-ink-600 mb-1">Username (usually your email)</span>
                                <input name="username" value="{{ old('username', $connection->imapCredential('username', $connection->isGmail() ? $connection->connected_email : '')) }}" autocomplete="username" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </label>
                            <label class="block">
                                <span class="block text-xs font-medium text-ink-600 mb-1">Password {{ $connection->hasImap() ? '(leave blank to keep)' : '' }}</span>
                                <input name="password" type="password" autocomplete="current-password" placeholder="{{ $connection->isGmail() ? '16-character App Password' : '••••••••' }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                            </label>
                        </div>
                        <p class="text-xs text-ink-600" data-preset-help></p>
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button variant="primary" icon="shield-check">{{ $connection->hasImap() ? 'Save & re-check inbox' : 'Turn on reply detection' }}</x-ui.button>
                            @if ($connection->hasImap())
                                <button type="submit" form="imap-destroy" class="rounded-full px-3 py-1.5 text-xs font-medium text-red-600 hover:bg-red-50">Turn off</button>
                            @endif
                        </div>
                    </form>
                    @if ($connection->hasImap())
                        <form id="imap-destroy" method="POST" action="{{ route('email-connections.imap.destroy') }}" onsubmit="return confirm('Turn off reply detection and delete the mailbox password?')">
                            @csrf @method('DELETE')
                        </form>
                    @endif
                @endif
            </div>

            <div class="rounded-2xl border border-dashed border-line bg-surface-muted/40 p-6">
                <div class="flex items-center gap-3">
                    <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-white ring-1 ring-line"><x-ui.brand-icon name="gmail" class="h-6 w-6" /></span>
                    <div>
                        <h3 class="font-display font-semibold text-navy-900">Gmail inbox reading</h3>
                        <p class="text-xs text-ink-600">Reply detection without an App Password</p>
                    </div>
                </div>
                @if ($connection?->canReadViaGmailApi())
                    <div class="mt-4"><x-ui.status-pill status="verified" label="Active" /></div>
                    <p class="mt-2 text-sm text-ink-600">We read new messages in your Gmail inbox to spot replies from your contacts — nothing else.</p>
                @elseif ($gmailReadingAvailable && $connection?->isGmail())
                    <p class="mt-4 text-sm text-ink-600">Allow AffilStack to read your inbox (read-only) so replies show up automatically.</p>
                    <div class="mt-4"><x-ui.oauth-button brand="google" label="Allow inbox reading" :href="route('email-connections.gmail.redirect')" /></div>
                @elseif ($gmailReadingAvailable)
                    <p class="mt-4 text-sm text-ink-600">Available when you send with Gmail — connect Gmail in step 1.</p>
                @else
                    <p class="mt-4 text-sm text-ink-600">Coming soon — waiting on Google's security review. Meanwhile, Gmail works perfectly over IMAP: use <strong>imap.gmail.com</strong> with an <a href="https://myaccount.google.com/apppasswords" target="_blank" rel="noopener" class="font-medium text-brand-600 underline">App Password</a>.</p>
                @endif
            </div>
        </div>
    </section>

    @if ($connection)
        {{-- 3. Limits & footer --}}
        <section class="mb-8" aria-labelledby="settings-heading">
            <div class="mb-3 flex items-center gap-2">
                <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] font-semibold text-white">3</span>
                <h2 id="settings-heading" class="font-display text-lg font-semibold text-navy-900">Sending limits &amp; email footer</h2>
            </div>
            <form method="POST" action="{{ route('email-connections.settings') }}" class="rounded-2xl border border-line bg-surface p-6 shadow-[0_1px_2px_rgba(16,24,40,0.04)]">
                @csrf @method('PATCH')
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Daily sending limit</span>
                        <input type="number" name="daily_limit" min="1" max="{{ $providerCaps[$connection->isGmail() ? 'gmail' : 'smtp'] }}" value="{{ old('daily_limit', $connection->daily_limit) }}" placeholder="{{ $providerCaps[$connection->isGmail() ? 'gmail' : 'smtp'] }} (maximum)" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="mt-1 block text-xs text-ink-400">Campaigns pause for the day at this number and continue tomorrow. New mailboxes: start around 50/day and grow slowly.</span>
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Sender name</span>
                        <input name="from_name" value="{{ old('from_name', $connection->credential('from_name')) }}" maxlength="255" placeholder="{{ auth()->user()->name }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="mt-1 block text-xs text-ink-400">Shown in the inbox and as your signature.</span>
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Business name (footer)</span>
                        <input name="footer_business_name" value="{{ old('footer_business_name', $connection->footer_business_name) }}" maxlength="255" placeholder="{{ $defaultBusinessName }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    </label>
                    <label class="block">
                        <span class="block text-xs font-medium text-ink-600 mb-1">Postal address (footer)</span>
                        <input name="footer_address" value="{{ old('footer_address', $connection->footer_address) }}" maxlength="500" placeholder="{{ $defaultAddress ?: 'Street, City, Country' }}" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                        <span class="mt-1 block text-xs text-ink-400">Anti-spam laws (CAN-SPAM, GDPR, NDPR) require a real address in marketing emails.</span>
                    </label>
                </div>
                <div class="mt-5"><x-ui.button icon="check">Save settings</x-ui.button></div>
            </form>
        </section>
    @endif

    {{-- 4. Deliverability --}}
    <section aria-labelledby="deliverability-heading">
        <div class="mb-3 flex items-center gap-2">
            <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-navy-900 text-[11px] font-semibold text-white">{{ $connection ? 4 : 3 }}</span>
            <h2 id="deliverability-heading" class="font-display text-lg font-semibold text-navy-900">Land in the inbox, not spam</h2>
        </div>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
            @foreach ([
                ['SPF', 'spf', 'A DNS TXT record listing who may send for your domain. Your email host gives you the exact value (e.g. "v=spf1 include:_spf.mail.hostinger.com ~all").'],
                ['DKIM', 'dkim', 'A digital signature on every email. Turn it on in your email host\'s panel (Hostinger: Emails → DNS settings) — it adds the record for you.'],
                ['DMARC', 'dmarc', 'Tells inboxes what to do with fakes. Start with a TXT record on _dmarc.yourdomain: "v=DMARC1; p=none; rua=mailto:you@yourdomain".'],
            ] as [$label, $key, $help])
                @php $state = $dns ? ($key === 'dkim' ? ($dns['managed'] ? true : null) : $dns[$key]) : null; @endphp
                <div class="rounded-2xl border border-line bg-surface p-5">
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-display font-semibold text-navy-900">{{ $label }}</h3>
                        @if ($state === true)
                            <x-ui.status-pill status="verified" :label="$dns['managed'] ? 'Handled by provider' : 'Found'" />
                        @elseif ($state === false)
                            <x-ui.status-pill status="failed" label="Missing" />
                        @else
                            <x-ui.status-pill status="not_connected" :label="$dns ? 'Check with your host' : 'Connect first'" />
                        @endif
                    </div>
                    <p class="mt-2 text-sm text-ink-600">{{ $help }}</p>
                    @if ($dns && ! $dns['managed'])
                        <p class="mt-2 font-mono text-[11px] text-ink-400">{{ $key === 'dmarc' ? '_dmarc.' : '' }}{{ $dns['domain'] }}</p>
                    @endif
                </div>
            @endforeach
        </div>
        <ul class="mt-4 grid gap-2 text-sm text-ink-600 sm:grid-cols-2">
            <li class="flex gap-2"><x-heroicon-m-light-bulb class="h-4 w-4 shrink-0 text-gold-500" /> Keep emails short, personal and plain — no big images or many links.</li>
            <li class="flex gap-2"><x-heroicon-m-light-bulb class="h-4 w-4 shrink-0 text-gold-500" /> Warm up new mailboxes: 20–50 emails a day for the first two weeks.</li>
            <li class="flex gap-2"><x-heroicon-m-light-bulb class="h-4 w-4 shrink-0 text-gold-500" /> Only email business contacts who'd plausibly want your offer.</li>
            <li class="flex gap-2"><x-heroicon-m-light-bulb class="h-4 w-4 shrink-0 text-gold-500" /> Every email includes an unsubscribe link — never remove it.</li>
        </ul>
    </section>

    @if ($connection?->isVerified())
        <x-ui.next-step class="mt-8" :href="route('crm.campaigns.create')" action="Create a campaign">
            Your sender is verified. Pick a list of prospects and let AI write your first campaign.
        </x-ui.next-step>
    @endif
@endsection

@push('scripts')
    <script>
        (function () {
            const presets = @json($presets);

            document.querySelectorAll('[data-preset-form]').forEach(function (form) {
                const kind = form.dataset.presetForm;
                const help = form.querySelector('[data-preset-help]');

                function apply(key, prefill) {
                    const preset = presets[key];
                    if (!preset) { return; }
                    if (prefill) {
                        Object.entries(preset[kind]).forEach(function ([field, value]) {
                            const input = form.querySelector('[data-field="' + field + '"]');
                            if (input && (value !== '' || field !== 'host')) { input.value = value; }
                            if (input && field === 'host' && value === '') { input.value = ''; input.focus(); }
                        });
                    }
                    if (help) {
                        help.textContent = preset.help || '';
                        if (preset.help_url) {
                            const link = document.createElement('a');
                            link.href = preset.help_url;
                            link.target = '_blank';
                            link.rel = 'noopener';
                            link.className = 'ml-1 font-medium text-brand-600 underline';
                            link.textContent = 'How to get it';
                            help.appendChild(link);
                        }
                    }
                }

                form.querySelectorAll('input[name="preset"], input[data-preset]').forEach(function (radio) {
                    radio.addEventListener('change', function () { apply(radio.value, true); });
                    if (radio.checked) { apply(radio.value, false); }
                });

                const port = form.querySelector('[data-field="port"]');
                const enc = form.querySelector('[data-field="encryption"]');
                if (port && enc) {
                    port.addEventListener('change', function () {
                        if (['465', '993'].includes(port.value)) { enc.value = 'ssl'; }
                        if (['587', '143', '2525'].includes(port.value)) { enc.value = 'tls'; }
                    });
                }
            });

            const same = document.getElementById('same-as-smtp');
            const login = document.getElementById('imap-login');
            if (same && login) {
                const sync = function () { login.hidden = same.checked; };
                same.addEventListener('change', sync);
                sync();
            }
        })();
    </script>
@endpush
