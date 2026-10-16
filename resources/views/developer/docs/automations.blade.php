@extends('developer.docs.layout')

@section('doc')
    <p>An automation recipe is a ready-made workflow for {{ implode(', ', $platforms) }} that uses AffilStack — for example "add new Typeform leads to my AffilStack CRM" or "post every new blog article to Slack".</p>

    <h2>What to include</h2>
    <ul>
        <li><strong>A template link</strong> — a shared Zap, a Make template or an n8n workflow link — and/or <strong>a blueprint file</strong>, the JSON export customers import.</li>
        <li><strong>Setup steps</strong> — what to connect, where to paste their AffilStack API key, and how to test it.</li>
    </ul>
    <p>Customers create an API key on their API &amp; Integrations page. Never put your own API key in a blueprint.</p>

    <h2>Checks before you submit</h2>
    <ul>
        <li>Remove personal data, test records and credentials from the export.</li>
        <li>Name every step clearly so customers can follow it.</li>
        <li>Say which paid plans of the automation platform it needs, if any.</li>
    </ul>
@endsection
