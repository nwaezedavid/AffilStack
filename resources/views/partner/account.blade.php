@extends('layouts.partner')

@section('title', 'Account & security')

@php
    $field = 'w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-emerald-600';
    $primary = 'rounded-full bg-emerald-900 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2';
    $secondary = 'rounded-full bg-white px-4 py-2 text-sm font-semibold text-navy-900 ring-1 ring-line hover:bg-surface-muted';
@endphp

@section('content')
    <div class="grid gap-6 lg:grid-cols-2">
        <x-ui.card id="leaderboard" title="Profile" description="How you appear to the AffilStack team." icon="user-circle">
            <form method="POST" action="{{ route('partner.account.profile') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="name" class="mb-1 block text-xs font-medium text-ink-900">Name</label>
                    <input id="name" name="name" value="{{ old('name', $user->name) }}" required autocomplete="name" class="{{ $field }}">
                </div>
                <div>
                    <label for="email" class="mb-1 block text-xs font-medium text-ink-900">Email</label>
                    <input id="email" value="{{ $user->email }}" disabled class="{{ $field }} bg-surface-muted text-ink-400">
                    <p class="mt-1 text-xs text-ink-400">To change your sign-in email, contact support.</p>
                </div>
                <div>
                    <label for="phone" class="mb-1 block text-xs font-medium text-ink-900">Phone or WhatsApp (optional)</label>
                    <input id="phone" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" class="{{ $field }}">
                </div>
                <label class="flex items-start gap-3 rounded-xl border border-line p-3">
                    <input type="checkbox" name="show_on_partner_leaderboard" value="1" @checked(old('show_on_partner_leaderboard', $user->show_on_partner_leaderboard)) class="mt-0.5 rounded border-line text-emerald-700 focus:ring-emerald-600">
                    <span class="text-sm">
                        <span class="font-medium text-navy-900">Show me on the partner leaderboard</span>
                        <span class="block text-xs text-ink-400">Only your first name and initial, never your earnings.</span>
                    </span>
                </label>
                <button type="submit" class="{{ $primary }}">Save profile</button>
            </form>
        </x-ui.card>

        <x-ui.card title="Password" description="Use a password you don't use anywhere else." icon="key">
            <form method="POST" action="{{ route('partner.account.password') }}" class="space-y-4">
                @csrf
                @method('PUT')
                <div>
                    <label for="current_password" class="mb-1 block text-xs font-medium text-ink-900">Current password</label>
                    <input id="current_password" type="password" name="current_password" required autocomplete="current-password" class="{{ $field }}">
                </div>
                <div>
                    <label for="new_password" class="mb-1 block text-xs font-medium text-ink-900">New password</label>
                    <input id="new_password" type="password" name="password" required autocomplete="new-password" class="{{ $field }}">
                </div>
                <div>
                    <label for="new_password_confirmation" class="mb-1 block text-xs font-medium text-ink-900">Confirm new password</label>
                    <input id="new_password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" class="{{ $field }}">
                </div>
                <button type="submit" class="{{ $primary }}">Change password</button>
            </form>
        </x-ui.card>
    </div>

    <x-ui.card id="two-factor" class="mt-6 scroll-mt-24" title="Two-step sign-in" description="Ask for a code from an authenticator app (Google Authenticator, Microsoft Authenticator, Authy, 1Password) whenever you sign in." icon="shield-check">
        <x-slot:headerActions>
            @if ($twoFactorEnabled)
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800"><x-heroicon-s-check-circle class="h-4 w-4" /> On</span>
            @else
                <span class="rounded-full bg-surface-muted px-2.5 py-1 text-xs font-semibold text-ink-600">Off</span>
            @endif
        </x-slot:headerActions>

        @if ($twoFactorPending)
            <div class="grid gap-6 md:grid-cols-[auto_minmax(0,1fr)]">
                <div class="rounded-xl bg-white p-3 ring-1 ring-line [&_svg]:h-44 [&_svg]:w-44">{!! $qrCode !!}</div>
                <div>
                    <ol class="list-decimal space-y-1.5 pl-5 text-sm text-ink-900">
                        <li>Open your authenticator app and add a new account.</li>
                        <li>Scan the QR code, or type this key: <code class="rounded bg-surface-muted px-1.5 py-0.5 font-mono text-xs">{{ trim(chunk_split($setupKey, 4, ' ')) }}</code></li>
                        <li>Enter the 6-digit code it shows.</li>
                    </ol>
                    <form method="POST" action="{{ route('partner.two-factor.confirm') }}" class="mt-4 flex flex-wrap gap-2">
                        @csrf
                        <label for="tf-code" class="sr-only">6-digit code</label>
                        <input id="tf-code" name="code" inputmode="numeric" autocomplete="one-time-code" maxlength="8" placeholder="123 456" required class="{{ $field }} w-40 font-mono tracking-widest">
                        <button type="submit" class="{{ $primary }}">Confirm and turn on</button>
                    </form>
                    <form method="POST" action="{{ route('partner.two-factor.disable') }}" class="mt-3 flex flex-wrap items-center gap-2">
                        @csrf
                        @method('DELETE')
                        <label for="cancel-password" class="sr-only">Password</label>
                        <input id="cancel-password" type="password" name="password" placeholder="Password" required autocomplete="current-password" class="{{ $field }} w-48">
                        <button type="submit" class="text-sm text-ink-600 underline hover:text-ink-900">Cancel setup</button>
                    </form>
                </div>
            </div>
        @elseif ($twoFactorEnabled)
            @if ($recoveryCodes)
                <div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4">
                    <p class="text-sm font-medium text-amber-900">Recovery codes</p>
                    <p class="mt-0.5 text-xs text-amber-800">Each code works once, if you lose your phone. Store them in a password manager or print them. They won't be shown again.</p>
                    <ul id="recovery-codes" class="mt-3 grid grid-cols-2 gap-1.5 font-mono text-sm text-navy-900 sm:grid-cols-4">
                        @foreach ($recoveryCodes as $code)
                            <li class="rounded bg-white px-2 py-1 ring-1 ring-amber-200">{{ $code }}</li>
                        @endforeach
                    </ul>
                    <x-ui.copy-button class="mt-3" :text="implode(PHP_EOL, $recoveryCodes)" label="Copy codes" />
                </div>
            @endif
            <p class="text-sm text-ink-600">You'll be asked for a code each time you sign in on a new session.</p>
            <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-end">
                <form method="POST" action="{{ route('partner.two-factor.recovery-codes') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <div>
                        <label for="rc-password" class="mb-1 block text-xs font-medium text-ink-900">Password</label>
                        <input id="rc-password" type="password" name="password" required autocomplete="current-password" class="{{ $field }} w-48">
                    </div>
                    <button type="submit" class="{{ $secondary }}">New recovery codes</button>
                </form>
                <form method="POST" action="{{ route('partner.two-factor.disable') }}" class="flex flex-wrap items-end gap-2" onsubmit="return confirm('Turn off two-step sign-in?')">
                    @csrf
                    @method('DELETE')
                    <div>
                        <label for="off-password" class="mb-1 block text-xs font-medium text-ink-900">Password</label>
                        <input id="off-password" type="password" name="password" required autocomplete="current-password" class="{{ $field }} w-48">
                    </div>
                    <button type="submit" class="rounded-full bg-white px-4 py-2 text-sm font-semibold text-rose-600 ring-1 ring-rose-200 hover:bg-rose-50">Turn off</button>
                </form>
            </div>
        @else
            <p class="text-sm text-ink-600">Even if someone learns your password, they can't get into your account (or change where your money goes) without your phone.</p>
            <form method="POST" action="{{ route('partner.two-factor.enable') }}" class="mt-4 flex flex-wrap items-end gap-2">
                @csrf
                <div>
                    <label for="on-password" class="mb-1 block text-xs font-medium text-ink-900">Confirm your password</label>
                    <input id="on-password" type="password" name="password" required autocomplete="current-password" class="{{ $field }} w-56">
                </div>
                <button type="submit" class="{{ $primary }}">Turn on two-step sign-in</button>
            </form>
        @endif
    </x-ui.card>
@endsection
