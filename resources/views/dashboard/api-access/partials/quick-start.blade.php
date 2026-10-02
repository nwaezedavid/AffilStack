@php
    $newToken = session('new_token');
    $sampleToken = $newToken ?: \App\Services\ApiDocs\CodeSampleGenerator::TOKEN_PLACEHOLDER;
    $meEndpoint = collect($endpoints)->firstWhere('id', 'getMe');
    $firstRequest = "curl \"{$baseUrl}/me\" \\\n  -H \"Authorization: Bearer {$sampleToken}\" \\\n  -H \"Accept: application/json\"";
@endphp

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">Quick start</h3>
    <p class="text-sm text-ink-600">Three steps from nothing to your first successful API call.</p>
</div>

<ol class="relative space-y-6 border-l-2 border-dashed border-line pl-6 ml-3">
    {{-- Step 1 --}}
    <li class="relative">
        <span class="absolute -left-[2.35rem] top-0 inline-flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 font-display text-sm font-semibold text-gold-300 ring-4 ring-surface-muted">1</span>
        <x-ui.card title="Create a token" description="A token is a password for apps. Give each integration its own so you can revoke one without breaking the others." icon="key">
            @if ($newToken)
                <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 p-4" role="status">
                    <p class="flex items-center gap-2 text-sm font-semibold text-emerald-900">
                        <x-heroicon-s-check-circle class="h-5 w-5 text-emerald-600" /> Your new token "{{ session('new_token_name') }}"
                    </p>
                    <p class="mt-1 text-xs text-emerald-800">Copy it now and store it somewhere safe (a password manager or your tool's secret settings). For your security we only show it once.</p>
                    <div class="mt-3 flex flex-col gap-2 sm:flex-row sm:items-center">
                        <code class="block flex-1 break-all rounded-lg bg-white px-3 py-2 font-mono text-sm text-navy-900 ring-1 ring-emerald-200" data-new-token>{{ $newToken }}</code>
                        <button type="button" data-copy-text="{{ $newToken }}" class="inline-flex shrink-0 items-center justify-center gap-1.5 rounded-full bg-emerald-600 px-4 py-2 text-sm font-medium text-white hover:bg-emerald-700">
                            <x-heroicon-m-clipboard-document class="h-4 w-4" /> <span data-copy-label>Copy token</span>
                        </button>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('api-access.tokens.store') }}" class="space-y-4">
                @csrf
                <div>
                    <label for="token-name" class="block text-sm font-medium text-navy-900">Token name</label>
                    <input id="token-name" name="name" required maxlength="255" placeholder="e.g. Zapier, Make — lead sync, WordPress site"
                        class="mt-1 w-full rounded-xl border border-line px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                </div>
                <fieldset>
                    <legend class="text-sm font-medium text-navy-900">What can it do?</legend>
                    <div class="mt-2 grid gap-2 sm:grid-cols-2">
                        <label class="relative flex cursor-pointer gap-3 rounded-xl border border-line p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-1 has-[:checked]:ring-brand-500">
                            <input type="radio" name="scope" value="full" checked class="mt-0.5 accent-brand-600">
                            <span>
                                <span class="block text-sm font-semibold text-navy-900">Read & write</span>
                                <span class="block text-xs text-ink-600">Full access: read data, add contacts, start research (may spend your API wallet).</span>
                            </span>
                        </label>
                        <label class="relative flex cursor-pointer gap-3 rounded-xl border border-line p-3 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/50 has-[:checked]:ring-1 has-[:checked]:ring-brand-500">
                            <input type="radio" name="scope" value="read_only" class="mt-0.5 accent-brand-600">
                            <span>
                                <span class="block text-sm font-semibold text-navy-900">Read only</span>
                                <span class="block text-xs text-ink-600">GET requests only — can never write data or spend the wallet. Ideal for reports and dashboards.</span>
                            </span>
                        </label>
                    </div>
                </fieldset>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl bg-amber-50/60 p-3 ring-1 ring-amber-200">
                    <input type="checkbox" name="is_sandbox" value="1" class="mt-0.5 accent-amber-600">
                    <span>
                        <span class="block text-sm font-semibold text-navy-900">Sandbox token (for testing)</span>
                        <span class="block text-xs text-ink-600">Works with separate test data only, never bills your wallet or credits, and returns instant sample research. Perfect while you build a Zap.</span>
                    </span>
                </label>
                <x-ui.button variant="primary" icon="plus">Generate token</x-ui.button>
            </form>

            <div class="mt-6">
                <p class="mb-2 text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Your tokens</p>
                @if ($tokens->isEmpty())
                    <p class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-sm text-ink-600">No tokens yet — generate one above to get started. A token created here also works for the Chrome extension, and vice versa.</p>
                @else
                    <div class="overflow-hidden rounded-xl border border-line">
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="bg-surface-muted text-left text-[11px] font-mono uppercase tracking-wide text-ink-400">
                                    <tr>
                                        <th class="px-4 py-2">Name</th>
                                        <th class="px-4 py-2">Access</th>
                                        <th class="px-4 py-2">Last 30 days</th>
                                        <th class="px-4 py-2">Last used</th>
                                        <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-line">
                                    @foreach ($tokens as $token)
                                        <tr>
                                            <td class="px-4 py-2.5 font-medium text-navy-900">{{ $token->name }}</td>
                                            <td class="px-4 py-2.5 whitespace-nowrap">
                                                <span @class([
                                                    'rounded-full px-2 py-0.5 text-xs font-medium ring-1 ring-inset',
                                                    'bg-brand-50 text-brand-700 ring-brand-600/20' => ! $token->isReadOnly(),
                                                    'bg-surface-muted text-ink-600 ring-line' => $token->isReadOnly(),
                                                ])>{{ $token->isReadOnly() ? 'Read only' : 'Read & write' }}</span>
                                                @if ($token->is_sandbox)
                                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700 ring-1 ring-inset ring-amber-600/20">Sandbox</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-ink-600">
                                                {{ $token->calls_30d }} call{{ $token->calls_30d === 1 ? '' : 's' }}
                                                @if ($token->spend_cents_30d > 0)
                                                    &middot; ${{ number_format($token->spend_cents_30d / 100, 2) }}
                                                @endif
                                            </td>
                                            <td class="px-4 py-2.5 whitespace-nowrap text-ink-600">{{ $token->last_used_at?->diffForHumans() ?? 'Never' }}</td>
                                            <td class="px-4 py-2.5 text-right">
                                                <form method="POST" action="{{ route('api-access.tokens.destroy', $token) }}" onsubmit="return confirm('Revoke this token? Anything using it will stop working immediately.')">
                                                    @csrf @method('DELETE')
                                                    <button class="rounded-full px-2.5 py-1 text-xs font-medium text-red-600 hover:bg-red-50">Revoke</button>
                                                </form>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>
        </x-ui.card>
    </li>

    {{-- Step 2 --}}
    <li class="relative">
        <span class="absolute -left-[2.35rem] top-0 inline-flex h-8 w-8 items-center justify-center rounded-full bg-navy-900 font-display text-sm font-semibold text-gold-300 ring-4 ring-surface-muted">2</span>
        <x-ui.card title="Make your first request" description="Ask the API who you are. If you get your name back, everything is wired up." icon="paper-airplane">
            <dl class="grid gap-3 sm:grid-cols-2">
                <div class="rounded-xl bg-surface-muted p-3">
                    <dt class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Base URL</dt>
                    <dd class="mt-1 flex items-center justify-between gap-2">
                        <code class="break-all font-mono text-xs text-navy-900">{{ $baseUrl }}</code>
                        <button type="button" data-copy-text="{{ $baseUrl }}" class="shrink-0 text-xs font-medium text-brand-600 hover:text-brand-700"><span data-copy-label>Copy</span></button>
                    </dd>
                </div>
                <div class="rounded-xl bg-surface-muted p-3">
                    <dt class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Auth header</dt>
                    <dd class="mt-1 flex items-center justify-between gap-2">
                        <code class="break-all font-mono text-xs text-navy-900">Authorization: Bearer {{ $newToken ? 'aff_…' : 'YOUR_API_TOKEN' }}</code>
                        <button type="button" data-copy-text="Authorization: Bearer {{ $sampleToken }}" class="shrink-0 text-xs font-medium text-brand-600 hover:text-brand-700"><span data-copy-label>Copy</span></button>
                    </dd>
                </div>
            </dl>
            <p class="mt-4 text-sm text-ink-600">Paste this into a terminal (Mac: Terminal, Windows: PowerShell){{ $newToken ? ' — your new token is already filled in' : ', replacing YOUR_API_TOKEN with your token' }}:</p>
            <x-ui.code-block class="mt-2" :code="$firstRequest" label="Terminal" />
            @if ($meEndpoint)
                <p class="mt-4 text-sm text-ink-600">You should get back something like this:</p>
                <x-ui.code-block class="mt-2" :code="json_encode($meEndpoint['response_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)" label="Response · 200 OK" />
            @endif
            <p class="mt-3 flex items-start gap-1.5 text-xs text-ink-600">
                <x-heroicon-m-information-circle class="mt-0.5 h-3.5 w-3.5 shrink-0 text-ink-400" />
                Got a 401? The token is missing, mistyped or revoked. No terminal? Use "Try it" on GET /me in the Endpoint reference.
            </p>
        </x-ui.card>
    </li>

    {{-- Step 3 --}}
    <li class="relative">
        <span class="absolute -left-[2.35rem] top-0 inline-flex h-8 w-8 items-center justify-center rounded-full bg-gold-400 font-display text-sm font-semibold text-navy-950 ring-4 ring-surface-muted">3</span>
        <x-ui.card title="Build something useful" description="Pick the path that suits you." icon="sparkles">
            <div class="grid gap-3 sm:grid-cols-3">
                @foreach ([
                    ['no-code', 'puzzle-piece', 'Use a no-code tool', 'Step-by-step for Zapier, Make, n8n, Google Sheets and Pabbly.'],
                    ['webhooks', 'bolt', 'Get live events', 'Have AffilStack call your URL when leads or content arrive.'],
                    ['code', 'code-bracket', 'Write code', 'Samples for every endpoint in six languages, plus SDKs.'],
                ] as [$tab, $icon, $title, $text])
                    <a href="{{ route('api-access.index', ['tab' => $tab]) }}" data-tab-link="{{ $tab }}" class="group rounded-xl border border-line p-4 transition hover:border-brand-500/40 hover:bg-brand-50/40">
                        <x-dynamic-component :component="'heroicon-o-'.$icon" class="h-6 w-6 text-brand-600" />
                        <span class="mt-2 block text-sm font-semibold text-navy-900">{{ $title }}</span>
                        <span class="mt-0.5 block text-xs text-ink-600">{{ $text }}</span>
                    </a>
                @endforeach
            </div>
        </x-ui.card>
    </li>
</ol>
