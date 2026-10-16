@extends('developer.docs.layout')

@section('doc')
    <p>A template pack is a set of ready-to-use templates. When a customer adds your pack, each template is copied into their <strong>Template Library</strong>, where they copy it into their content, emails and campaigns.</p>

    <h2>Template types</h2>
    <ul>
        @foreach ($kinds as $label)
            <li>{{ $label }}</li>
        @endforeach
    </ul>

    <h2>Writing good templates</h2>
    <ul>
        <li>Give each one a clear title that says when to use it — "Cold email: first touch for SaaS offers".</li>
        <li>Mark the parts customers fill in with square brackets: <code>[PRODUCT]</code>, <code>[AUDIENCE]</code>, <code>[YOUR LINK]</code>.</li>
        <li>Follow affiliate disclosure rules: don't write fake reviews, income claims or anything that pretends to be a real person's experience.</li>
        <li>Up to 200 templates per pack, 10,000 characters each.</li>
    </ul>
    <p>Updating a pack (a new approved version) doesn't change templates customers already have; new customers get the new set.</p>
@endsection
