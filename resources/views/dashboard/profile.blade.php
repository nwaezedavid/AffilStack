@extends('layouts.app')

@section('title', 'Profile & Security')

@section('content')
    <div class="max-w-xl space-y-6">
        <div class="bg-surface border border-line rounded-lg p-6">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-1">Account</h3>
            <p class="text-sm text-ink-600">{{ $user->name }} · {{ $user->email }}</p>
        </div>

        <div class="bg-surface border border-line rounded-lg p-6">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Two-factor authentication</h3>

            @if ($user->hasEnabledTwoFactorAuthentication())
                <p class="text-sm text-emerald-700 font-medium mb-4">✓ Enabled — your account requires an authenticator code to sign in.</p>

                @if ($recoveryCodes)
                    <details class="mb-4">
                        <summary class="cursor-pointer text-sm text-brand-600 hover:text-brand-700">View recovery codes</summary>
                        <p class="text-xs text-ink-600 mt-2 mb-2">Store these somewhere safe. Each one can be used once if you lose access to your authenticator app.</p>
                        <div class="grid grid-cols-2 gap-1 font-mono text-xs bg-surface-muted rounded-md p-3">
                            @foreach ($recoveryCodes as $code)
                                <span>{{ $code }}</span>
                            @endforeach
                        </div>
                    </details>
                @endif

                <div class="flex gap-2">
                    <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                        @csrf
                        <button class="rounded-md border border-line text-xs px-3 py-1.5 hover:bg-surface-muted transition">Regenerate recovery codes</button>
                    </form>
                    <form method="POST" action="{{ route('two-factor.disable') }}">
                        @csrf @method('DELETE')
                        <button class="rounded-md border border-red-200 text-red-700 text-xs px-3 py-1.5 hover:bg-red-50 transition">Disable 2FA</button>
                    </form>
                </div>
            @elseif ($qrSvg)
                <p class="text-sm text-ink-600 mb-3">Scan this with Google Authenticator, Authy, or 1Password, then enter the 6-digit code to confirm.</p>
                <div class="bg-white inline-block p-3 rounded-md border border-line mb-4">{!! $qrSvg !!}</div>
                <form method="POST" action="{{ route('two-factor.confirm') }}" class="flex gap-2">
                    @csrf
                    <input name="code" inputmode="numeric" placeholder="6-digit code" required
                           class="rounded-md border border-line px-3 py-1.5 text-sm w-40 focus:outline-none focus:ring-2 focus:ring-brand-500">
                    <button class="rounded-md bg-navy-900 text-white text-sm px-3 py-1.5 hover:bg-navy-800 transition">Confirm</button>
                </form>
                <form method="POST" action="{{ route('two-factor.disable') }}" class="mt-2">
                    @csrf @method('DELETE')
                    <button class="text-xs text-ink-400 hover:text-ink-600">Cancel setup</button>
                </form>
            @else
                <p class="text-sm text-ink-600 mb-3">Add an extra layer of security to your account with an authenticator app. Strongly recommended for admin accounts.</p>
                <form method="POST" action="{{ route('two-factor.enable') }}">
                    @csrf
                    <button class="rounded-md bg-navy-900 text-white text-sm px-4 py-2 hover:bg-navy-800 transition">Enable two-factor authentication</button>
                </form>
            @endif
        </div>
    </div>
@endsection
