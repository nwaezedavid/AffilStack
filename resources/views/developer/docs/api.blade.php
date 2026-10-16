@extends('developer.docs.layout')

@section('doc')
    <p>Call the AffilStack REST API with the customer's access token. Everything is scoped to that customer's account and to the permissions they allowed.</p>
    @php($example = 'curl '.url('/api/v1/offers')." \\\n  -H \"Authorization: Bearer afat_…\" \\\n  -H \"Accept: application/json\"")
    <x-ui.code-block label="Example" :code="$example" />

    <h2>Endpoints apps can use</h2>
    <ul>
        <li><code>GET /api/v1/me</code> — <code>profile:read</code></li>
        <li><code>GET /api/v1/offers</code>, <code>GET /api/v1/offers/{id}</code> — <code>offers:read</code></li>
        <li><code>POST /api/v1/offers</code> — <code>offers:write</code> (starts product research; uses the customer's API wallet like any API call)</li>
        <li><code>GET /api/v1/generations</code>, <code>GET /api/v1/generations/{id}</code> — <code>content:read</code></li>
        <li><code>GET /api/v1/crm-contacts</code>, <code>GET /api/v1/crm-contacts/{id}</code> — <code>crm:read</code></li>
        <li><code>POST /api/v1/crm-contacts</code>, <code>POST /api/v1/crm-contacts/bulk</code>, <code>PATCH /api/v1/crm-contacts/{id}</code> — <code>crm:write</code></li>
        <li><code>GET /api/v1/referrals/summary</code> — <code>referrals:read</code></li>
    </ul>
    <p>The full request and response formats are in the <a href="{{ route('api.v1.openapi') }}">OpenAPI specification</a>. Other endpoints (admin, the browser extension, Zapier subscriptions) aren't available to apps.</p>

    <h2>Errors</h2>
    <ul>
        <li><code>401</code> — the token is missing, expired, revoked, or the customer no longer has your app. Refresh the token; if that fails, ask the customer to connect again.</li>
        <li><code>403</code> with <code>required_scope</code> — the customer didn't give your app that permission.</li>
        <li><code>429</code> — rate limited. Limits follow the customer's plan; back off and retry.</li>
    </ul>

    <h2>Idempotency</h2>
    <p>Send an <code>Idempotency-Key</code> header on <code>POST /offers</code> and <code>POST /crm-contacts</code> so a retried request isn't applied twice.</p>
@endsection
