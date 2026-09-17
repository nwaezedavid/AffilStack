@extends('layouts.app')

@section('title', 'API Access')

@section('content')
    <p class="text-sm text-ink-600 max-w-lg mb-6">
        Connect your own tools to AffilStack — pull your offers, generated content, and CRM contacts, or push new
        contacts in automatically. Generate a token below and use it as a Bearer token against the endpoints listed
        further down.
    </p>

    {{-- Tokens --}}
    <div class="bg-surface border border-line rounded-lg p-5 mb-6">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Your tokens</h3>
        <p class="text-sm text-ink-600 mb-4">Give each integration its own token so you can revoke one without affecting the others. A token created here also works for the browser extension, and vice versa.</p>

        <form method="POST" action="{{ route('api-access.tokens.store') }}" class="flex items-center gap-2 mb-5 flex-wrap">
            @csrf
            <input name="name" required placeholder="e.g. Zapier" class="rounded-md border border-line px-3 py-2 text-sm">
            <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Generate token</button>
        </form>

        @if ($tokens->isEmpty())
            <p class="text-sm text-ink-500">No tokens yet — generate one above to get started.</p>
        @else
            <div class="border border-line rounded-md overflow-hidden">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                        <tr>
                            <th class="text-left px-4 py-2">Name</th>
                            <th class="text-left px-4 py-2">Last used</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($tokens as $token)
                            <tr>
                                <td class="px-4 py-2.5 font-medium text-ink-900">{{ $token->name }}</td>
                                <td class="px-4 py-2.5 text-ink-600">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('api-access.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- Reference --}}
    <div class="bg-surface border border-line rounded-lg p-5">
        <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Endpoints</h3>
        <p class="text-sm text-ink-600 mb-4">
            Base URL: <code class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">{{ url('/api/v1') }}</code>
            &middot; Auth header: <code class="font-mono text-xs bg-surface-muted px-1.5 py-0.5 rounded">Authorization: Bearer &lt;token&gt;</code>
            &middot; 60 requests/minute per token.
        </p>
        <div class="border border-line rounded-md overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-surface-muted text-xs uppercase tracking-wide text-ink-400 font-mono">
                    <tr>
                        <th class="text-left px-4 py-2">Method</th>
                        <th class="text-left px-4 py-2">Path</th>
                        <th class="text-left px-4 py-2">What it does</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line font-mono text-xs">
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/me</td><td class="px-4 py-2 font-sans text-ink-600">Your account, plan, and credit balance</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/offers</td><td class="px-4 py-2 font-sans text-ink-600">List your offers</td></tr>
                    <tr><td class="px-4 py-2">POST</td><td class="px-4 py-2">/offers</td><td class="px-4 py-2 font-sans text-ink-600">Queue new offer research</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/offers/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">A single offer</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/generations</td><td class="px-4 py-2 font-sans text-ink-600">List generated content (filter by offer_id, module)</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/generations/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">A single piece of generated content</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/crm-contacts</td><td class="px-4 py-2 font-sans text-ink-600">List your CRM contacts</td></tr>
                    <tr><td class="px-4 py-2">POST</td><td class="px-4 py-2">/crm-contacts</td><td class="px-4 py-2 font-sans text-ink-600">Add a new contact</td></tr>
                    <tr><td class="px-4 py-2">PATCH</td><td class="px-4 py-2">/crm-contacts/{{ '{id}' }}</td><td class="px-4 py-2 font-sans text-ink-600">Update a contact</td></tr>
                    <tr><td class="px-4 py-2">GET</td><td class="px-4 py-2">/referrals/summary</td><td class="px-4 py-2 font-sans text-ink-600">Your referral link and commission totals</td></tr>
                </tbody>
            </table>
        </div>
    </div>
@endsection
