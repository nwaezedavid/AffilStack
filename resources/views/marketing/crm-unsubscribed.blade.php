@extends('layouts.marketing')

@section('title', 'Unsubscribed — '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))

@section('content')
    <section class="max-w-lg mx-auto px-6 py-20 text-center">
        <h1 class="font-display font-semibold text-2xl text-navy-900 text-wrap-balance">You're unsubscribed</h1>
        <p class="text-ink-600 mt-3">
            You won't receive any further emails from {{ $senderName ?: 'this sender' }} through AffilStack.
        </p>
    </section>
@endsection
