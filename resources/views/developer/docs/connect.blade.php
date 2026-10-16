@extends('developer.docs.layout')

@section('doc')
    <p>Connect with AffilStack is standard <strong>OAuth 2.0 authorization code</strong> flow, with optional PKCE. The customer chooses what your app may access, and can disconnect it at any time.</p>

    <h2>1. Send the customer to AffilStack</h2>
    <p>From your Connect page, redirect the customer to:</p>
    <x-ui.code-block label="Authorization URL" :code="route('oauth.authorize').'?response_type=code&client_id=afapp_…&redirect_uri=https%3A%2F%2Fyourapp.com%2Fcallback&scope=profile:read%20offers:read&state=RANDOM&code_challenge=…&code_challenge_method=S256'" />
    <ul>
        <li><code>redirect_uri</code> must exactly match one of your registered redirect URLs.</li>
        <li><code>scope</code> is a space-separated list of permissions from your listing. Leave it out to ask for all of them.</li>
        <li><code>state</code> is a random value you check when the customer comes back.</li>
        <li><code>code_challenge</code> (S256) is recommended. If you send one, send the matching <code>code_verifier</code> when exchanging the code.</li>
    </ul>
    <p>If your app is paid, customers who haven't subscribed are asked to subscribe first and then return to the same screen.</p>

    <h2>2. Get the code back</h2>
    <p>When the customer clicks <strong>Allow</strong>, AffilStack redirects to your <code>redirect_uri</code> with <code>?code=…&amp;state=…</code>. If they cancel you get <code>?error=access_denied&amp;state=…</code>. Codes expire after {{ config('marketplace.authorization_code_minutes') }} minutes and work once.</p>

    <h2>3. Exchange the code for tokens</h2>
    <x-ui.code-block label="Request" :code="'curl -X POST '.route('api.oauth.token').' \\
  -d grant_type=authorization_code \\
  -d code=THE_CODE \\
  -d redirect_uri=https://yourapp.com/callback \\
  -d code_verifier=THE_VERIFIER \\
  -d client_id=afapp_… \\
  -d client_secret=afsec_…'" />
    @php($tokenResponse = json_encode(['access_token' => 'afat_…', 'token_type' => 'Bearer', 'expires_in' => config('marketplace.access_token_minutes') * 60, 'refresh_token' => 'afrt_…', 'scope' => 'profile:read offers:read'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))
    <x-ui.code-block label="Response" :code="$tokenResponse" />
    <p>You can also send the client ID and secret with HTTP Basic auth.</p>

    <h2>4. Refresh</h2>
    <p>Access tokens last {{ config('marketplace.access_token_minutes') }} minutes. Get a new pair with <code>grant_type=refresh_token</code> and <code>refresh_token=afrt_…</code>. Each refresh token works once — store the new one. Refresh tokens expire after {{ config('marketplace.refresh_token_days') }} days without use.</p>

    <h2>Revoking</h2>
    <p>POST <code>token=…</code> with your client credentials to <code>{{ route('api.oauth.revoke') }}</code> when a customer disconnects inside your app. When a customer removes your app in AffilStack, or their subscription ends, every token stops working.</p>

    <h2>Permissions</h2>
    <ul>
        @foreach ($scopes as $scope => $description)
            <li><code>{{ $scope }}</code> — {{ $description }}</li>
        @endforeach
    </ul>
@endsection
