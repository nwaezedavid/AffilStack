@extends('developer.docs.layout')

@section('doc')
    <p>AffilStack is an all-in-one platform for affiliate marketers: product research, content, CRM and outreach, link tracking and earnings. The marketplace lets you sell to those customers with three kinds of add-on.</p>
    <ul>
        <li><strong>Connected apps</strong> — your own web app connects to customers' AffilStack accounts with <a href="{{ route('developer.docs', 'connect') }}">Connect with AffilStack</a>, then uses the <a href="{{ route('developer.docs', 'api') }}">API</a> and <a href="{{ route('developer.docs', 'webhooks') }}">webhooks</a>.</li>
        <li><strong>Template packs</strong> — prompts, emails, posts, hooks and scripts added to the customer's template library. <a href="{{ route('developer.docs', 'template-packs') }}">More</a>.</li>
        <li><strong>Automation recipes</strong> — Zapier, Make or n8n workflows customers copy. <a href="{{ route('developer.docs', 'automations') }}">More</a>.</li>
    </ul>

    <h2>1. Become a developer</h2>
    <p>Sign in with your AffilStack account, add a short profile (the name and support email customers see) and accept the developer terms.</p>

    <h2>2. Create your add-on</h2>
    <p>Choose the type and a name. Connected apps get a <strong>client ID</strong> and <strong>client secret</strong> immediately — the secret is shown once, so store it on your server.</p>

    <h2>3. Fill in the listing</h2>
    <p>Add an icon, tagline, description, screenshots and pricing, plus the type's settings: redirect URLs and permissions for apps, the templates for a pack, or the platform, link and setup steps for a recipe.</p>

    <h2>4. Try it in your own account</h2>
    <p>Your draft works in your own AffilStack account straight away, so you can connect your app or install your pack before anyone else sees it. Paid add-ons are free for you.</p>

    <h2>5. Submit for review</h2>
    <p>Our team checks every new add-on and every update before customers see it — see the <a href="{{ route('developer.docs', 'review') }}">review guidelines</a>. We email you when it's approved or if changes are needed. Customers keep using the live version while an update is in review.</p>

    <h2>6. Earn</h2>
    <p>Customers subscribe monthly through AffilStack and you receive {{ rtrim(rtrim(number_format($share, 2), '0'), '.') }}% of each payment. See <a href="{{ route('developer.docs', 'payouts') }}">pricing & payouts</a>.</p>
@endsection
