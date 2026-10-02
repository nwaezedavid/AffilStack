<div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <h3 class="font-display text-lg font-semibold text-navy-900">Endpoint reference</h3>
        <p class="text-sm text-ink-600">Every endpoint, straight from the <a href="{{ $openApiUrl }}" class="font-medium text-brand-600 underline">OpenAPI spec</a> — what it does, what to send, what you get back.</p>
    </div>
    <label class="relative block sm:w-64">
        <span class="sr-only">Filter endpoints</span>
        <x-heroicon-m-magnifying-glass class="pointer-events-none absolute left-3 top-2.5 h-4 w-4 text-ink-400" />
        <input type="search" placeholder="Filter, e.g. contacts" data-endpoint-filter class="w-full rounded-full border border-line bg-white py-2 pl-9 pr-3 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
    </label>
</div>

<div class="grid gap-3 rounded-2xl border border-line bg-surface p-4 sm:grid-cols-3">
    <div>
        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Base URL</p>
        <p class="mt-1 break-all font-mono text-xs text-navy-900">{{ $baseUrl }}</p>
    </div>
    <div>
        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Authentication</p>
        <p class="mt-1 font-mono text-xs text-navy-900">Authorization: Bearer aff_…</p>
    </div>
    <div>
        <p class="text-[11px] font-mono uppercase tracking-[0.14em] text-ink-400">Format</p>
        <p class="mt-1 text-xs text-navy-900">JSON in, JSON out · send <code class="font-mono">Accept: application/json</code></p>
    </div>
</div>

<div class="rounded-2xl border border-brand-100 bg-brand-50/60 p-4">
    <label for="try-token" class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-heroicon-o-play-circle class="h-5 w-5 text-brand-600" /> Try it live</label>
    <p class="mt-0.5 text-xs text-ink-600">Paste a token to run any GET endpoint below from your browser. It stays in this tab's memory only — never saved or sent anywhere but the API. A read-only or sandbox token is perfect here.</p>
    <input id="try-token" type="password" autocomplete="off" spellcheck="false" placeholder="aff_…" data-try-token class="mt-2 w-full rounded-xl border border-line bg-white px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 sm:max-w-md">
</div>

@foreach ($endpointGroups as $tag => $groupEndpoints)
    <div data-endpoint-group>
        <h4 class="mb-2 mt-2 text-[11px] font-mono uppercase tracking-[0.16em] text-ink-400">{{ $tag }}</h4>
        <div class="space-y-3">
            @foreach ($groupEndpoints as $endpoint)
                <details id="{{ $endpoint['anchor'] }}" data-endpoint-card data-search="{{ strtolower($endpoint['method'].' '.$endpoint['path'].' '.$endpoint['summary'].' '.$tag) }}"
                    class="group scroll-mt-6 rounded-2xl border border-line bg-surface shadow-[0_1px_2px_rgba(16,24,40,0.04)] open:ring-1 open:ring-brand-500/30">
                    <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-2 px-4 py-3.5 sm:flex-nowrap">
                        <x-api.method-badge :method="$endpoint['method']" />
                        <code class="font-mono text-sm font-medium text-navy-900">{{ $endpoint['path'] }}</code>
                        <span class="order-last w-full truncate text-sm text-ink-600 sm:order-none sm:w-auto sm:flex-1">{{ $endpoint['summary'] }}</span>
                        <span class="flex shrink-0 items-center gap-1.5">
                            @if ($endpoint['cost_cents'] > 0)
                                <span class="rounded-full bg-gold-300/25 px-2 py-0.5 text-[11px] font-semibold text-gold-600">${{ number_format($endpoint['cost_cents'] / 100, 2) }}</span>
                            @endif
                            <span @class([
                                'rounded-full px-2 py-0.5 text-[11px] font-medium ring-1 ring-inset',
                                'bg-emerald-50 text-emerald-700 ring-emerald-600/20' => $endpoint['scope'] === 'read',
                                'bg-brand-50 text-brand-700 ring-brand-600/20' => $endpoint['scope'] === 'write',
                            ])>{{ $endpoint['scope'] === 'read' ? 'read' : 'write' }}</span>
                            <x-heroicon-m-chevron-down class="h-4 w-4 text-ink-400 transition group-open:rotate-180" />
                        </span>
                    </summary>

                    <div class="space-y-5 border-t border-line px-4 py-5 sm:px-5">
                        @if ($endpoint['description'] || $endpoint['note'])
                            <div class="space-y-2 text-sm text-ink-600">
                                @if ($endpoint['note'])
                                    <p>{{ $endpoint['note'] }}</p>
                                @endif
                                @if ($endpoint['description'])
                                    <p class="whitespace-pre-line">{{ $endpoint['description'] }}</p>
                                @endif
                            </div>
                        @endif

                        <dl class="grid gap-2 sm:grid-cols-3">
                            <div class="rounded-xl bg-surface-muted px-3 py-2">
                                <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">Token needed</dt>
                                <dd class="text-xs text-navy-900">{{ $endpoint['scope'] === 'read' ? 'Any token (read-only works)' : 'Read & write token' }}</dd>
                            </div>
                            <div class="rounded-xl bg-surface-muted px-3 py-2">
                                <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">Cost</dt>
                                <dd class="text-xs text-navy-900">
                                    @if ($endpoint['cost_cents'] > 0)
                                        ${{ number_format($endpoint['cost_cents'] / 100, 2) }} per call from the API wallet (free with a sandbox token; refunded if the call fails)
                                    @else
                                        Free
                                    @endif
                                </dd>
                            </div>
                            <div class="rounded-xl bg-surface-muted px-3 py-2">
                                <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">Safe to retry</dt>
                                <dd class="text-xs text-navy-900">
                                    @if ($endpoint['idempotent'])
                                        Yes — send an Idempotency-Key header
                                    @elseif ($endpoint['scope'] === 'read' || $endpoint['method'] === 'PATCH' || $endpoint['method'] === 'DELETE')
                                        Yes — repeating it changes nothing extra
                                    @else
                                        Retry only the failed items
                                    @endif
                                </dd>
                            </div>
                        </dl>

                        @if ($endpoint['path_params'] || $endpoint['query_params'])
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-600">Parameters</p>
                                <div class="overflow-x-auto rounded-xl border border-line">
                                    <table class="w-full text-sm">
                                        <tbody class="divide-y divide-line">
                                            @foreach ([...array_map(fn ($p) => [...$p, 'in' => 'path'], $endpoint['path_params']), ...array_map(fn ($p) => [...$p, 'in' => 'query'], $endpoint['query_params'])] as $param)
                                                <tr>
                                                    <td class="whitespace-nowrap px-3 py-2 align-top"><code class="font-mono text-xs font-semibold text-navy-900">{{ $param['name'] }}</code></td>
                                                    <td class="whitespace-nowrap px-3 py-2 align-top text-xs text-ink-600">{{ $param['type'] }} · in {{ $param['in'] }}</td>
                                                    <td class="px-3 py-2 align-top text-xs text-ink-600">
                                                        @if ($param['required'])<span class="mr-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-rose-700">required</span>@endif
                                                        {{ $param['description'] ?? '' }}
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        @if ($endpoint['body_fields'])
                            <div>
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-600">Request body (JSON)</p>
                                <div class="overflow-x-auto rounded-xl border border-line">
                                    <table class="w-full text-sm">
                                        <tbody class="divide-y divide-line">
                                            @foreach ($endpoint['body_fields'] as $field)
                                                <tr>
                                                    <td class="whitespace-nowrap px-3 py-2 align-top"><code class="font-mono text-xs font-semibold text-navy-900">{{ $field['name'] }}</code></td>
                                                    <td class="whitespace-nowrap px-3 py-2 align-top text-xs text-ink-600">{{ $field['type'] }}</td>
                                                    <td class="px-3 py-2 align-top text-xs text-ink-600">
                                                        @if ($field['required'])<span class="mr-1 rounded bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-rose-700">required</span>@endif
                                                        {{ $field['description'] ?? '' }}
                                                        @if ($field['enum'])
                                                            One of: {{ implode(', ', $field['enum']) }}.
                                                        @endif
                                                        @if ($field['max'])
                                                            <span class="text-ink-400">Max {{ $field['max'] }}.</span>
                                                        @endif
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-4 xl:grid-cols-2">
                            <div class="min-w-0">
                                <div class="mb-2 flex items-center justify-between gap-2">
                                    <p class="text-xs font-semibold uppercase tracking-wide text-ink-600">Example request</p>
                                    <a href="{{ route('api-access.index', ['tab' => 'code', 'endpoint' => $endpoint['id']]) }}" data-tab-link="code" data-code-endpoint="{{ $endpoint['id'] }}" class="text-xs font-medium text-brand-600 hover:text-brand-700">JavaScript, Python, PHP →</a>
                                </div>
                                <x-ui.code-block :code="$codeSamples[$endpoint['id']]['curl']" label="cURL" />
                            </div>
                            <div class="min-w-0">
                                <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-600">Example response</p>
                                <x-ui.code-block :code="$endpoint['response_example'] !== null ? json_encode($endpoint['response_example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : '(empty body)'" :label="$endpoint['success_status'].' '.($endpoint['success_status'] === 201 ? 'Created' : 'OK')" />
                            </div>
                        </div>

                        <div>
                            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-ink-600">Possible responses</p>
                            <ul class="flex flex-wrap gap-2">
                                @foreach ($endpoint['responses'] as $response)
                                    <li class="inline-flex items-center gap-1.5 rounded-full bg-surface-muted px-2.5 py-1 text-xs text-ink-600 ring-1 ring-inset ring-line">
                                        <span @class(['font-mono font-semibold', 'text-emerald-700' => str_starts_with($response['status'], '2'), 'text-rose-700' => ! str_starts_with($response['status'], '2')])>{{ $response['status'] }}</span>
                                        {{ $response['description'] }}
                                    </li>
                                @endforeach
                            </ul>
                        </div>

                        @if ($endpoint['safe'])
                            <form class="rounded-xl border border-dashed border-brand-500/40 bg-brand-50/30 p-4" data-try-form data-method="GET" data-path="{{ $endpoint['path'] }}">
                                <p class="flex items-center gap-2 text-sm font-semibold text-navy-900"><x-heroicon-m-play class="h-4 w-4 text-brand-600" /> Try it</p>
                                @if ($endpoint['path_params'] || $endpoint['query_params'])
                                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                                        @foreach ($endpoint['path_params'] as $param)
                                            <label class="block text-xs text-ink-600">{{ $param['name'] }} <span class="text-rose-600">*</span>
                                                <input name="{{ $param['name'] }}" data-in="path" required inputmode="numeric" placeholder="e.g. 1" class="mt-1 w-full rounded-lg border border-line bg-white px-2.5 py-1.5 font-mono text-sm">
                                            </label>
                                        @endforeach
                                        @foreach ($endpoint['query_params'] as $param)
                                            <label class="block text-xs text-ink-600">{{ $param['name'] }}
                                                <input name="{{ $param['name'] }}" data-in="query" placeholder="{{ $param['example'] }}" class="mt-1 w-full rounded-lg border border-line bg-white px-2.5 py-1.5 font-mono text-sm">
                                            </label>
                                        @endforeach
                                    </div>
                                @endif
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <x-ui.button size="sm" variant="brand" icon="paper-airplane">Send request</x-ui.button>
                                    <span class="text-xs text-ink-600" data-try-status></span>
                                </div>
                                <pre class="mt-3 hidden max-h-80 overflow-auto rounded-xl bg-navy-950 px-4 py-3 font-mono text-[12.5px] text-slate-100" data-try-output></pre>
                            </form>
                        @endif
                    </div>
                </details>
            @endforeach
        </div>
    </div>
@endforeach

@push('scripts')
    <script>
        (function () {
            const baseUrl = @json($baseUrl);
            const tokenInput = document.querySelector('[data-try-token]');

            document.querySelectorAll('[data-try-form]').forEach(function (form) {
                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    const status = form.querySelector('[data-try-status]');
                    const output = form.querySelector('[data-try-output]');
                    const token = (tokenInput.value || '').trim();
                    if (!token) {
                        status.textContent = 'Paste a token in “Try it live” above first.';
                        tokenInput.focus();
                        return;
                    }
                    let path = form.dataset.path;
                    const query = new URLSearchParams();
                    form.querySelectorAll('input[data-in]').forEach(function (input) {
                        const value = input.value.trim();
                        if (input.dataset.in === 'path') {
                            path = path.replace('{' + input.name + '}', encodeURIComponent(value));
                        } else if (value !== '') {
                            query.set(input.name, value);
                        }
                    });
                    const url = baseUrl + path + (query.toString() ? '?' + query.toString() : '');
                    status.textContent = 'Sending…';
                    const started = performance.now();
                    fetch(url, { headers: { Authorization: 'Bearer ' + token, Accept: 'application/json' }, credentials: 'omit' })
                        .then(function (response) {
                            return response.text().then(function (text) {
                                let pretty = text;
                                try { pretty = JSON.stringify(JSON.parse(text), null, 2); } catch (e) {}
                                status.textContent = response.status + ' ' + (response.ok ? 'OK' : 'Error') + ' · ' + Math.round(performance.now() - started) + ' ms';
                                status.className = 'text-xs font-semibold ' + (response.ok ? 'text-emerald-700' : 'text-rose-700');
                                output.textContent = pretty || '(empty body)';
                                output.classList.remove('hidden');
                            });
                        })
                        .catch(function () {
                            status.textContent = 'Network error — check your connection and try again.';
                        });
                });
            });

            const filter = document.querySelector('[data-endpoint-filter]');
            filter && filter.addEventListener('input', function () {
                const term = filter.value.trim().toLowerCase();
                document.querySelectorAll('[data-endpoint-group]').forEach(function (group) {
                    let visible = 0;
                    group.querySelectorAll('[data-endpoint-card]').forEach(function (card) {
                        const match = !term || card.dataset.search.includes(term);
                        card.classList.toggle('hidden', !match);
                        visible += match ? 1 : 0;
                    });
                    group.classList.toggle('hidden', visible === 0);
                });
            });

            // Deep links (#endpoint-…) open the matching card.
            if (location.hash) {
                const target = document.getElementById(location.hash.slice(1));
                if (target && target.tagName === 'DETAILS') { target.open = true; }
            }
        })();
    </script>
@endpush
