@extends('layouts.guest')

@section('title', ($subscribed ? 'Subscribed' : 'Unsubscribed').' · '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))

@section('content')
    <div class="text-center">
        <span @class([
            'mx-auto inline-flex h-12 w-12 items-center justify-center rounded-full',
            'bg-emerald-50 text-emerald-600' => $subscribed,
            'bg-brand-50 text-brand-600' => ! $subscribed,
        ])>
            @if ($subscribed)
                <x-heroicon-o-check-circle class="h-6 w-6" />
            @else
                <x-heroicon-o-envelope class="h-6 w-6" />
            @endif
        </span>

        @if ($subscribed)
            <h1 class="mt-4 font-display font-semibold text-xl text-navy-900">You're back on the list</h1>
            <p class="mt-2 text-sm text-ink-600">
                We'll send you birthday, holiday and tips emails again. You can change this any time from Profile &amp; Security &rarr; Notifications.
            </p>
        @else
            <h1 class="mt-4 font-display font-semibold text-xl text-navy-900">You're unsubscribed</h1>
            <p class="mt-2 text-sm text-ink-600">
                We won't send birthday, holiday or tips emails to {{ $user->email }} any more. You'll still get important account emails, like receipts, security alerts and replies to your support tickets.
            </p>
            <form method="POST" action="{{ $resubscribeUrl }}" class="mt-6">
                @csrf
                <x-ui.button variant="secondary" icon="arrow-uturn-left">Changed your mind? Resubscribe</x-ui.button>
            </form>
        @endif

        <a href="{{ route('login') }}" class="mt-6 inline-block text-sm text-brand-600 hover:text-brand-700">Go to {{ \App\Models\SiteSetting::get('site_name', 'AffilStack') }}</a>
    </div>
@endsection
