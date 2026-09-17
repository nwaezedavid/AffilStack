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

    {{-- Outbound webhooks (audit gap #7) — owner-only, see ApiAccessController --}}
    @if (! auth()->user()->isSeat())
        <div class="bg-surface border border-line rounded-lg p-5 mb-6">
            <h3 class="font-display font-semibold text-sm text-navy-900 mb-2">Outbound webhooks</h3>
            <p class="text-sm text-ink-600 mb-4">
                Get a signed HTTP POST the moment something happens, instead of polling the API above. Each delivery
                carries an <code class="font-mono text-xs bg-surface-muted px-1 py-0.5 rounded">X-AffilStack-Signature</code>
                header — an HMAC-SHA256 of the raw request body using the endpoint's secret below — so you can verify
                it actually came from AffilStack.
            </p>

            <form method="POST" action="{{ route('api-access.webhooks.store') }}" class="mb-5 space-y-2">
                @csrf
                <div class="flex items-center gap-2 flex-wrap">
                    <input type="url" name="url" required placeholder="https://your-app.example.com/webhooks/affilstack"
                           class="rounded-md border border-line px-3 py-2 text-sm flex-1 min-w-[16rem]">
                    <button class="rounded-md border border-line text-ink-900 text-sm px-3 py-2 hover:bg-surface-muted transition">Add endpoint</button>
                </div>
                <div class="flex flex-wrap gap-3 text-xs text-ink-700">
                    @foreach (config('webhooks.events') as $event => $label)
                        <label class="flex items-center gap-1.5">
                            <input type="checkbox" name="events[]" value="{{ $event }}" checked>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
                @error('url')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
                @error('events')
                    <p class="text-xs text-red-600">{{ $message }}</p>
                @enderror
            </form>

            @if ($webhookEndpoints->isEmpty())
                <p class="text-sm text-ink-500">No webhook endpoints yet — add one above to get started.</p>
            @else
                <div class="space-y-3">
                    @foreach ($webhookEndpoints as $endpoint)
                        <div class="border border-line rounded-md p-3">
                            <div class="flex items-center justify-between gap-3 flex-wrap">
                                <div>
                                    <p class="text-sm font-medium text-ink-900 break-all">{{ $endpoint->url }}</p>
                                    <p class="text-xs text-ink-500 mt-0.5">
                                        {{ collect($endpoint->events)->map(fn ($e) => config('webhooks.events')[$e] ?? $e)->implode(', ') }}
                                    </p>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span @class([
                                        'text-xs font-mono px-2 py-0.5 rounded',
                                        'bg-emerald-50 text-emerald-700' => $endpoint->is_active,
                                        'bg-surface-muted text-ink-500' => ! $endpoint->is_active,
                                    ])>{{ $endpoint->is_active ? 'Active' : 'Disabled' }}</span>
                                    <form method="POST" action="{{ route('api-access.webhooks.toggle', $endpoint) }}">
                                        @csrf @method('PATCH')
                                        <button class="text-xs text-brand-600 hover:text-brand-700">{{ $endpoint->is_active ? 'Disable' : 'Enable' }}</button>
                                    </form>
                                    <form method="POST" action="{{ route('api-access.webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Remove this webhook endpoint?')">
                                        @csrf @method('DELETE')
                                        <button class="text-xs text-red-600 hover:text-red-700">Remove</button>
                                    </form>
                                </div>
                            </div>
                            <p class="text-xs text-ink-500 mt-2">
                                Signing secret: <code class="font-mono bg-surface-muted px-1.5 py-0.5 rounded">{{ $endpoint->secret }}</code>
                                &middot; Last delivered: {{ $endpoint->last_triggered_at?->diffForHumans() ?? 'Never' }}
                            </p>
                            @if ($endpoint->deliveries->isNotEmpty())
                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @foreach ($endpoint->deliveries as $delivery)
                                        <span title="{{ $delivery->event }} — {{ $delivery->created_at->diffForHumans() }}" @class([
                                            'text-[11px] font-mono px-1.5 py-0.5 rounded',
                                            'bg-emerald-50 text-emerald-700' => $delivery->status === 'delivered',
                                            'bg-red-50 text-red-700' => $delivery->status === 'failed',
                                            'bg-amber-50 text-amber-700' => $delivery->status === 'pending',
                                        ])>{{ $delivery->event }}: {{ $delivery->status }}</span>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endif

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
