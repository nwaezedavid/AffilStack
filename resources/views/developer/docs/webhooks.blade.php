@extends('developer.docs.layout')

@section('doc')
    <p>Set a webhook URL and choose events on your listing. When a customer connects your app, AffilStack sends you those events for their account — only the ones their permissions cover.</p>

    <h2>Events</h2>
    <ul>
        @foreach ($events as $event => $scope)
            <li><code>{{ $event }}</code> — needs <code>{{ $scope }}</code></li>
        @endforeach
    </ul>

    <h2>Delivery</h2>
    <p>Each event is a JSON <code>POST</code>:</p>
    @php($sample = json_encode(['event' => 'crm_contact.created', 'data' => ['id' => 123, 'email' => 'buyer@example.com']], JSON_PRETTY_PRINT))
    <x-ui.code-block label="Body" :code="$sample" />
    <p>Headers include <code>X-AffilStack-Event</code> and <code>X-AffilStack-Signature</code>, an HMAC-SHA256 of the raw body using your app's <strong>webhook signing secret</strong> (on your add-on's page). Always check it:</p>
    @php($verify = "\$expected = hash_hmac('sha256', \$rawBody, \$webhookSecret);\nif (! hash_equals(\$expected, \$_SERVER['HTTP_X_AFFILSTACK_SIGNATURE'] ?? '')) {\n    http_response_code(401);\n    exit;\n}")
    <x-ui.code-block label="PHP" :code="$verify" />
    <p>Reply with any <code>2xx</code> within a few seconds. Failed deliveries are retried up to {{ config('webhooks.max_attempts') }} times over several hours.</p>
    <p>Your webhook URL must be a public <code>https://</code> address.</p>
@endsection
