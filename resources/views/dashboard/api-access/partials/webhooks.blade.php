@php
    $signatureSamples = [
        'node' => <<<'JS'
// Express — keep the RAW body: the signature is computed over the exact bytes we send.
const crypto = require('crypto');
const express = require('express');
const app = express();

app.post('/webhooks/affilstack', express.raw({ type: 'application/json' }), (req, res) => {
  const expected = crypto
    .createHmac('sha256', process.env.AFFILSTACK_WEBHOOK_SECRET) // whsec_…
    .update(req.body)
    .digest('hex');
  const received = req.get('X-AffilStack-Signature') || '';

  if (received.length !== expected.length ||
      !crypto.timingSafeEqual(Buffer.from(received), Buffer.from(expected))) {
    return res.status(401).send('Bad signature');
  }

  const { event, data, delivery_id } = JSON.parse(req.body);
  // Use delivery_id to ignore a delivery you've already processed.
  console.log(event, data);
  res.sendStatus(200); // reply 2xx quickly, do slow work afterwards
});
JS,
        'python' => <<<'PY'
# Flask
import hashlib, hmac, os
from flask import Flask, request, abort

app = Flask(__name__)

@app.post("/webhooks/affilstack")
def affilstack_webhook():
    secret = os.environ["AFFILSTACK_WEBHOOK_SECRET"].encode()  # whsec_…
    expected = hmac.new(secret, request.get_data(), hashlib.sha256).hexdigest()
    received = request.headers.get("X-AffilStack-Signature", "")

    if not hmac.compare_digest(received, expected):
        abort(401)

    payload = request.get_json()
    print(payload["event"], payload["data"])
    return "", 200
PY,
        'php' => <<<'PHP'
<?php
// Plain PHP (works the same inside Laravel or WordPress).
$secret = getenv('AFFILSTACK_WEBHOOK_SECRET'); // whsec_…
$body = file_get_contents('php://input');
$expected = hash_hmac('sha256', $body, $secret);
$received = $_SERVER['HTTP_X_AFFILSTACK_SIGNATURE'] ?? '';

if (! hash_equals($expected, $received)) {
    http_response_code(401);
    exit('Bad signature');
}

$payload = json_decode($body, true);
// $payload['event'], $payload['data'], $payload['delivery_id']
http_response_code(200);
PHP,
    ];
    $backoff = collect(config('webhooks.retry_backoff_minutes'))->map(fn ($m) => $m >= 60 ? ($m / 60).'h' : $m.'m')->implode(', ');
@endphp

<div>
    <h3 class="font-display text-lg font-semibold text-navy-900">Webhooks</h3>
    <p class="text-sm text-ink-600">Instead of asking the API "anything new?" over and over, give AffilStack a URL and it will POST to it the moment something happens.</p>
</div>

@if (! $isSeat)
    <x-ui.card title="Outbound webhooks" description="Each delivery is a signed JSON POST. Add the URL from Zapier, Make, n8n, Pabbly or your own server." icon="bolt">
        <form method="POST" action="{{ route('api-access.webhooks.store') }}" class="space-y-3">
            @csrf
            <div>
                <label for="webhook-url" class="block text-sm font-medium text-navy-900">Endpoint URL</label>
                <div class="mt-1 flex flex-col gap-2 sm:flex-row">
                    <input id="webhook-url" type="url" name="url" required placeholder="https://hooks.zapier.com/hooks/catch/…" value="{{ old('url') }}"
                        class="min-w-0 flex-1 rounded-xl border border-line px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                    <x-ui.button variant="primary" icon="plus">Add endpoint</x-ui.button>
                </div>
                @error('url')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <fieldset>
                <legend class="text-sm font-medium text-navy-900">Send me</legend>
                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                    @foreach (config('webhooks.events') as $event => $label)
                        <label class="flex cursor-pointer items-center gap-2 rounded-lg border border-line px-3 py-2 text-sm text-ink-900 has-[:checked]:border-brand-500 has-[:checked]:bg-brand-50/40">
                            <input type="checkbox" name="events[]" value="{{ $event }}" checked class="accent-brand-600">
                            <span class="min-w-0"><span class="block">{{ $label }}</span><span class="block font-mono text-[11px] text-ink-400">{{ $event }}</span></span>
                        </label>
                    @endforeach
                </div>
                @error('events')
                    <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                @enderror
            </fieldset>
        </form>

        <div class="mt-6 space-y-3">
            @forelse ($webhookEndpoints as $endpoint)
                <div class="rounded-xl border border-line p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <x-ui.status-pill :status="$endpoint->is_active ? 'verified' : 'not_connected'" :label="$endpoint->is_active ? 'Active' : 'Disabled'" />
                                @if (str_contains($endpoint->url, 'hooks.zapier.com'))
                                    <x-ui.brand-icon name="zapier" class="h-4 w-4" />
                                @endif
                            </div>
                            <p class="mt-1.5 break-all text-sm font-medium text-navy-900">{{ $endpoint->url }}</p>
                            <p class="mt-0.5 text-xs text-ink-600">{{ collect($endpoint->events)->map(fn ($e) => config('webhooks.events')[$e] ?? $e)->implode(' · ') }}</p>
                        </div>
                        <div class="flex shrink-0 flex-wrap items-center gap-2">
                            @if ($endpoint->is_active)
                                <form method="POST" action="{{ route('api-access.webhooks.test', $endpoint) }}">
                                    @csrf
                                    <x-ui.button size="sm" variant="brand" icon="paper-airplane">Send test event</x-ui.button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('api-access.webhooks.toggle', $endpoint) }}">
                                @csrf @method('PATCH')
                                <x-ui.button size="sm" variant="secondary">{{ $endpoint->is_active ? 'Disable' : 'Enable' }}</x-ui.button>
                            </form>
                            <form method="POST" action="{{ route('api-access.webhooks.destroy', $endpoint) }}" onsubmit="return confirm('Remove this webhook endpoint? It will stop receiving deliveries immediately.')">
                                @csrf @method('DELETE')
                                <x-ui.button size="sm" variant="danger">Remove</x-ui.button>
                            </form>
                        </div>
                    </div>
                    <div class="mt-3 flex flex-col gap-2 rounded-lg bg-surface-muted px-3 py-2 text-xs text-ink-600 sm:flex-row sm:items-center sm:justify-between">
                        <span class="flex min-w-0 items-center gap-2">
                            Signing secret:
                            <code class="truncate font-mono text-navy-900" data-secret="{{ $endpoint->secret }}">whsec_••••••••••••</code>
                            <button type="button" class="shrink-0 font-medium text-brand-600 hover:text-brand-700" data-reveal-secret>Show</button>
                            <button type="button" class="shrink-0 font-medium text-brand-600 hover:text-brand-700" data-copy-text="{{ $endpoint->secret }}"><span data-copy-label>Copy</span></button>
                        </span>
                        <span>Last delivered: {{ $endpoint->last_triggered_at?->diffForHumans() ?? 'Never' }}</span>
                    </div>
                </div>
            @empty
                <x-ui.empty-state icon="bolt" title="No webhook endpoints yet">
                    Add a URL above — Zapier's "Catch Hook", Make's "Custom webhook" or your own server — and send a test event.
                </x-ui.empty-state>
            @endforelse
        </div>
    </x-ui.card>

    <x-ui.card title="Delivery log" description="The last 20 deliveries across your endpoints. Failed deliveries retry automatically ({{ config('webhooks.max_attempts') }} attempts: after {{ $backoff }})." icon="queue-list" padding="p-0">
        @if ($deliveryLog->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-ink-600">Nothing delivered yet. Deliveries appear here as soon as an event fires or you send a test event.</p>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="bg-surface-muted text-left text-[11px] font-mono uppercase tracking-wide text-ink-400">
                        <tr>
                            <th class="px-4 py-2">Event</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Attempts</th>
                            <th class="px-4 py-2">Response</th>
                            <th class="px-4 py-2">When</th>
                            <th class="px-4 py-2"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($deliveryLog as $delivery)
                            <tr>
                                <td class="px-4 py-2.5">
                                    <span class="font-mono text-xs text-navy-900">{{ $delivery->event }}</span>
                                    @if ($delivery->payload['test'] ?? false)
                                        <span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold uppercase text-amber-700">test</span>
                                    @endif
                                    <span class="block max-w-[14rem] truncate text-[11px] text-ink-400">{{ $delivery->endpoint?->url }}</span>
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap">
                                    @if ($delivery->status === 'delivered')
                                        <x-ui.status-pill status="verified" label="Delivered" />
                                    @elseif ($delivery->status === 'pending' && $delivery->attempts > 0)
                                        <x-ui.status-pill status="checking" label="Retrying" />
                                    @elseif ($delivery->status === 'pending')
                                        <x-ui.status-pill status="connected" label="Queued" />
                                    @else
                                        <x-ui.status-pill status="failed" label="Failed" />
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-xs text-ink-600">{{ $delivery->attempts }} / {{ config('webhooks.max_attempts') }}</td>
                                <td class="px-4 py-2.5 text-xs text-ink-600" title="{{ $delivery->response_body }}">
                                    @if ($delivery->response_status)
                                        <span @class(['font-mono font-semibold', 'text-emerald-700' => $delivery->response_status < 300, 'text-rose-700' => $delivery->response_status >= 300])>{{ $delivery->response_status }}</span>
                                    @else
                                        <span class="block max-w-[12rem] truncate">{{ $delivery->response_body ? \Illuminate\Support\Str::limit($delivery->response_body, 40) : '—' }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 whitespace-nowrap text-xs text-ink-600">{{ $delivery->created_at->diffForHumans() }}</td>
                                <td class="px-4 py-2.5 text-right">
                                    <form method="POST" action="{{ route('api-access.webhooks.deliveries.replay', $delivery) }}">
                                        @csrf
                                        <button class="rounded-full px-2.5 py-1 text-xs font-medium text-brand-600 hover:bg-brand-50">Replay</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
@else
    <div class="flex items-start gap-3 rounded-2xl border border-line bg-surface p-4 text-sm text-ink-600">
        <x-heroicon-o-information-circle class="h-5 w-5 shrink-0 text-brand-600" />
        Webhook endpoints are managed by your account owner. The event catalogue below shows what they can send.
    </div>
@endif

<x-ui.card title="Event catalogue" description="Every event you can subscribe to and the exact JSON body we POST." icon="rectangle-stack">
    <div class="space-y-3">
        @foreach ($webhookEvents as $event => $info)
            <details class="group rounded-xl border border-line">
                <summary class="flex cursor-pointer list-none flex-wrap items-center gap-x-3 gap-y-1 px-4 py-3">
                    <code class="font-mono text-sm font-semibold text-navy-900">{{ $event }}</code>
                    <span class="text-sm text-ink-600">{{ $info['label'] }}</span>
                    <x-heroicon-m-chevron-down class="ml-auto h-4 w-4 text-ink-400 transition group-open:rotate-180" />
                </summary>
                <div class="space-y-3 border-t border-line px-4 py-4">
                    <p class="text-sm text-ink-600"><span class="font-semibold text-navy-900">Fires when:</span> {{ $info['fires_when'] }}</p>
                    <x-ui.code-block :code="json_encode(app(\App\Services\ApiDocs\WebhookEventCatalog::class)->sampleBody($event), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)" label="POST body" />
                </div>
            </details>
        @endforeach
    </div>
</x-ui.card>

<x-ui.card title="Verify it's really from AffilStack" description="Recommended for your own servers; Zapier/Make users can skip this." icon="finger-print">
    <dl class="mb-4 grid gap-2 sm:grid-cols-3">
        <div class="rounded-xl bg-surface-muted px-3 py-2">
            <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">X-AffilStack-Signature</dt>
            <dd class="text-xs text-navy-900">HMAC-SHA256 of the raw body, hex, keyed with the endpoint's signing secret</dd>
        </div>
        <div class="rounded-xl bg-surface-muted px-3 py-2">
            <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">X-AffilStack-Event</dt>
            <dd class="text-xs text-navy-900">The event name, e.g. crm_contact.created</dd>
        </div>
        <div class="rounded-xl bg-surface-muted px-3 py-2">
            <dt class="text-[11px] font-mono uppercase tracking-wide text-ink-400">Your reply</dt>
            <dd class="text-xs text-navy-900">Any 2xx within 10 seconds counts as delivered; anything else is retried</dd>
        </div>
    </dl>
    <x-api.code-tabs :samples="$signatureSamples" :languages="['node' => 'Node.js', 'python' => 'Python', 'php' => 'PHP']" />
</x-ui.card>

@push('scripts')
    <script>
        document.addEventListener('click', function (event) {
            const button = event.target.closest('[data-reveal-secret]');
            if (!button) {
                return;
            }
            const code = button.parentElement.querySelector('[data-secret]');
            const showing = button.textContent.trim() === 'Hide';
            code.textContent = showing ? 'whsec_••••••••••••' : code.dataset.secret;
            button.textContent = showing ? 'Show' : 'Hide';
        });
    </script>
@endpush
