@extends('layouts.app')

@section('title', 'Email Sending')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Connect your own Gmail or SMTP server so your CRM nurture emails send from your own address instead of
        AffilStack's shared one — better deliverability for you, and it protects AffilStack's sending reputation for
        everyone. Once connected, every nurture send routes through it exclusively.
    </p>

    @if ($connection)
        <div class="bg-surface border border-line rounded-lg p-5 mb-6">
            <div class="flex items-center justify-between flex-wrap gap-3 mb-2">
                <h3 class="font-display font-semibold text-sm text-navy-900">
                    {{ $connection->isGmail() ? 'Gmail' : 'SMTP' }} connected
                </h3>
                <form method="POST" action="{{ route('email-connections.destroy') }}" onsubmit="return confirm('Disconnect? Nurture emails will go back to sending from AffilStack\'s own address.')">
                    @csrf @method('DELETE')
                    <button class="text-xs text-red-600 hover:text-red-700">Disconnect</button>
                </form>
            </div>
            <p class="text-sm text-ink-600">Sending as <span class="font-medium text-ink-900">{{ $connection->connected_email }}</span></p>
            @if ($connection->verification_status === 'success')
                <p class="text-xs text-emerald-700 mt-2">✓ {{ $connection->verification_message }}</p>
            @elseif ($connection->verification_status === 'failed')
                <p class="text-xs text-red-600 mt-2">⚠ {{ $connection->verification_message }}</p>
            @endif
        </div>
    @else
        <div class="grid gap-5 md:grid-cols-2 mb-6">
            {{-- Gmail --}}
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Connect Gmail</h3>
                @if ($gmailAvailable)
                    <p class="text-sm text-ink-600 mb-4">Sign in with the Google account you want nurture emails to send from.</p>
                    <a href="{{ route('email-connections.gmail.redirect') }}" class="inline-flex items-center gap-2 rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">
                        Connect Gmail
                    </a>
                @else
                    <p class="text-sm text-ink-500">Gmail connections aren't available yet — use SMTP below instead, or check back soon.</p>
                @endif
            </div>

            {{-- SMTP --}}
            <div class="bg-surface border border-line rounded-lg p-5">
                <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Connect SMTP</h3>
                <p class="text-sm text-ink-600 mb-4">Use your own domain's mail server. We'll send a test email to confirm it works.</p>
                <form method="POST" action="{{ route('email-connections.smtp.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-2 gap-3">
                        <input name="host" required placeholder="smtp.yourdomain.com" class="rounded-md border border-line px-3 py-2 text-sm col-span-2 sm:col-span-1">
                        <input name="port" type="number" required placeholder="587" class="rounded-md border border-line px-3 py-2 text-sm">
                    </div>
                    <input name="username" required placeholder="Username" class="w-full rounded-md border border-line px-3 py-2 text-sm">
                    <input name="password" type="password" required placeholder="Password" class="w-full rounded-md border border-line px-3 py-2 text-sm">
                    <div class="grid grid-cols-2 gap-3">
                        <input name="from_email" type="email" required placeholder="you@yourdomain.com" class="rounded-md border border-line px-3 py-2 text-sm">
                        <input name="from_name" placeholder="From name (optional)" class="rounded-md border border-line px-3 py-2 text-sm">
                    </div>
                    <button class="w-full rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Connect &amp; send test email</button>
                </form>
            </div>
        </div>
    @endif
@endsection
