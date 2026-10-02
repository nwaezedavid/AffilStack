{{-- SMTP sender form, saved and verified live in one step. --}}
<form method="POST" action="{{ route('email-connections.smtp.store') }}" class="space-y-4" data-preset-form="smtp">
    @csrf
    @include('dashboard.email-connection.partials.presets', ['kind' => 'smtp', 'selected' => $smtp?->preset ?? 'hostinger'])

    <div class="grid grid-cols-1 gap-3 sm:grid-cols-[minmax(0,1fr)_7rem_9rem]">
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">SMTP server</span>
            <input name="host" data-field="host" required value="{{ old('host', $smtp?->credential('host') ?? config('crm.presets.hostinger.smtp.host')) }}" placeholder="smtp.yourdomain.com" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Port</span>
            <select name="port" data-field="port" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @foreach ([465, 587, 2525, 25] as $port)
                    <option value="{{ $port }}" @selected((int) old('port', $smtp?->credential('port') ?? 465) === $port)>{{ $port }}</option>
                @endforeach
            </select>
        </label>
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Security</span>
            <select name="encryption" data-field="encryption" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                <option value="ssl" @selected(old('encryption', $smtp?->credential('encryption') ?? 'ssl') === 'ssl')>SSL/TLS</option>
                <option value="tls" @selected(old('encryption', $smtp?->credential('encryption')) === 'tls')>STARTTLS</option>
            </select>
        </label>
    </div>
    <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Username (usually your full email)</span>
            <input name="username" required autocomplete="username" value="{{ old('username', $smtp?->credential('username')) }}" placeholder="you@yourdomain.com" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Password {{ $smtp ? '(leave blank to keep)' : '' }}</span>
            <input name="password" type="password" @if (! $smtp) required @endif autocomplete="current-password" placeholder="Mailbox or app password" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Send from (email)</span>
            <input name="from_email" type="email" required value="{{ old('from_email', $smtp?->credential('from_email')) }}" placeholder="you@yourdomain.com" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
        <label class="block">
            <span class="block text-xs font-medium text-ink-600 mb-1">Send from (name)</span>
            <input name="from_name" value="{{ old('from_name', $smtp?->credential('from_name') ?? auth()->user()->name) }}" placeholder="Your name" class="w-full rounded-lg border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </label>
    </div>
    <p class="flex gap-2 rounded-xl bg-brand-50/70 px-3 py-2.5 text-xs text-ink-600"><x-heroicon-m-information-circle class="h-4 w-4 shrink-0 text-brand-600" /><span data-preset-help></span></p>
    <div class="flex flex-wrap items-center justify-between gap-3">
        <label class="inline-flex items-center gap-2 text-sm text-ink-900">
            <input type="hidden" name="send_test" value="0">
            <input type="checkbox" name="send_test" value="1" checked class="h-4 w-4 rounded border-line text-brand-600 focus:ring-brand-500">
            Email me a test message
        </label>
        <x-ui.button variant="primary" icon="shield-check">Save &amp; verify</x-ui.button>
    </div>
    <p class="flex items-center gap-1.5 text-[11px] text-ink-400"><x-heroicon-m-lock-closed class="h-3.5 w-3.5" /> Passwords are encrypted at rest and only used to send your CRM emails.</p>
</form>
