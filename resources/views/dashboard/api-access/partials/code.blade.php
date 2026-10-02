<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">Code samples</h3>
    <p class="text-sm text-ink-600">Pick an endpoint and a language. Every sample is generated from the API specification and already points at your base URL — replace <code class="rounded bg-surface-muted px-1 font-mono text-xs">YOUR_API_TOKEN</code> and run it.</p>
</div>

<div class="grid gap-4 md:grid-cols-3">
    <div class="rounded-2xl border border-line bg-surface p-4">
        <div class="flex items-center gap-2"><x-ui.brand-icon name="nodedotjs" class="h-5 w-5" /><p class="font-display font-semibold text-navy-900">Node.js SDK</p></div>
        <p class="mt-1 text-xs text-ink-600">One file, zero dependencies, Node 18+. Every endpoint as a method, with errors as exceptions.</p>
        <x-ui.button class="mt-3" size="sm" variant="secondary" :href="route('api-access.sdk', 'js')" icon="arrow-down-tray">Download affilstack.js</x-ui.button>
    </div>
    <div class="rounded-2xl border border-line bg-surface p-4">
        <div class="flex items-center gap-2"><x-ui.brand-icon name="python" class="h-5 w-5" /><p class="font-display font-semibold text-navy-900">Python SDK</p></div>
        <p class="mt-1 text-xs text-ink-600">One file built on <code class="font-mono">requests</code>. Mirrors the Node SDK method for method.</p>
        <x-ui.button class="mt-3" size="sm" variant="secondary" :href="route('api-access.sdk', 'python')" icon="arrow-down-tray">Download affilstack.py</x-ui.button>
    </div>
    <div class="rounded-2xl border border-line bg-surface p-4">
        <div class="flex items-center gap-2"><x-ui.brand-icon name="postman" class="h-5 w-5" /><p class="font-display font-semibold text-navy-900">Postman & Insomnia</p></div>
        <p class="mt-1 text-xs text-ink-600">Import the OpenAPI spec (File → Import → Link) to get every endpoint as a ready request.</p>
        <button type="button" data-copy-text="{{ $openApiUrl }}" class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1.5 text-xs font-medium text-navy-900 ring-1 ring-line hover:bg-surface-muted">
            <x-heroicon-m-link class="h-4 w-4" /> <span data-copy-label>Copy spec link</span>
        </button>
    </div>
</div>

<x-ui.card padding="p-5 sm:p-6">
    <label for="code-endpoint" class="block text-sm font-medium text-navy-900">Endpoint</label>
    <select id="code-endpoint" class="mt-1 w-full rounded-xl border border-line bg-white px-3 py-2 font-mono text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30" data-endpoint-select>
        @foreach ($endpointGroups as $tag => $groupEndpoints)
            <optgroup label="{{ $tag }}">
                @foreach ($groupEndpoints as $endpoint)
                    <option value="{{ $endpoint['id'] }}" @selected(request('endpoint') === $endpoint['id'])>{{ $endpoint['method'] }} {{ $endpoint['path'] }} — {{ $endpoint['summary'] }}</option>
                @endforeach
            </optgroup>
        @endforeach
    </select>

    @php($selectedEndpoint = request('endpoint') && isset($codeSamples[request('endpoint')]) ? request('endpoint') : ($endpoints[0]['id'] ?? null))
    <div class="mt-5">
        @foreach ($endpoints as $endpoint)
            <div data-endpoint-samples="{{ $endpoint['id'] }}" @class(['hidden' => $endpoint['id'] !== $selectedEndpoint])>
                <div class="mb-3 flex flex-wrap items-center gap-2">
                    <x-api.method-badge :method="$endpoint['method']" />
                    <code class="font-mono text-sm text-navy-900">{{ $endpoint['path'] }}</code>
                    <a href="{{ route('api-access.index', ['tab' => 'reference']) }}#{{ $endpoint['anchor'] }}" data-tab-link="reference" data-target="{{ $endpoint['anchor'] }}" class="ml-auto text-xs font-medium text-brand-600 hover:text-brand-700">Full reference →</a>
                </div>
                <x-api.code-tabs :samples="$codeSamples[$endpoint['id']]" />
            </div>
        @endforeach
    </div>
</x-ui.card>

<x-ui.card title="Good to know" icon="light-bulb">
    <ul class="grid gap-3 text-sm text-ink-600 sm:grid-cols-2">
        <li class="flex gap-2"><x-heroicon-m-lock-closed class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> Run these on a server or in an automation tool. Never put a token in website JavaScript, a mobile app or a public repo.</li>
        <li class="flex gap-2"><x-heroicon-m-arrow-path class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> Sending an <code class="font-mono text-xs">Idempotency-Key</code> lets you retry a create safely — the same key never creates a second record.</li>
        <li class="flex gap-2"><x-heroicon-m-beaker class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> Build against a sandbox token first: identical responses, no charges, separate test data.</li>
        <li class="flex gap-2"><x-heroicon-m-queue-list class="mt-0.5 h-4 w-4 shrink-0 text-brand-600" /> List endpoints are paginated — read <code class="font-mono text-xs">last_page</code> and ask for <code class="font-mono text-xs">?page=2</code>, <code class="font-mono text-xs">3</code>…</li>
    </ul>
</x-ui.card>

@push('scripts')
    <script>
        (function () {
            const select = document.querySelector('[data-endpoint-select]');
            if (!select) {
                return;
            }
            select.addEventListener('change', function () {
                document.querySelectorAll('[data-endpoint-samples]').forEach(function (panel) {
                    panel.classList.toggle('hidden', panel.dataset.endpointSamples !== select.value);
                });
            });
            // "Other languages" links from the reference pre-select their endpoint here.
            document.addEventListener('click', function (event) {
                const link = event.target.closest('[data-code-endpoint]');
                if (link) {
                    select.value = link.dataset.codeEndpoint;
                    select.dispatchEvent(new Event('change'));
                }
            });
        })();
    </script>
@endpush
