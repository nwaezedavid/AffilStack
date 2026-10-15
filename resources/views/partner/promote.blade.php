@extends('layouts.partner')

@section('title', 'Emails & posts')

@section('content')
    <p class="mb-6 max-w-2xl text-sm text-ink-600">Ready-made emails and social posts with your link already inside. Copy, tweak a line so it sounds like you, and send.</p>

    <section aria-labelledby="emails-heading">
        <h2 id="emails-heading" class="mb-4 font-display text-lg font-semibold text-navy-900">Email swipes</h2>
        @include('dashboard.referrals.partials.emails')
    </section>

    <section class="mt-10" aria-labelledby="social-heading">
        <h2 id="social-heading" class="mb-4 font-display text-lg font-semibold text-navy-900">Social posts</h2>
        @include('dashboard.referrals.partials.social')
    </section>
@endsection
