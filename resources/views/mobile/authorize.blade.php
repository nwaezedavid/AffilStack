@extends('layouts.guest')

@section('title', 'Sign in to the '.\App\Support\Brand::name().' app')

@section('content')
    <div class="flex justify-center">
        @if ($brandIcon = \App\Support\Brand::iconUrl())
            <img src="{{ $brandIcon }}" alt="{{ \App\Support\Brand::name() }}" class="h-14 w-14 rounded-xl object-contain">
        @else
            <span class="inline-flex h-14 w-14 items-center justify-center rounded-xl bg-navy-900 font-display text-base font-semibold text-gold-400" aria-hidden="true">{{ mb_strtoupper(mb_substr(\App\Support\Brand::name(), 0, 2)) }}</span>
        @endif
    </div>

    <h1 class="mt-5 text-center font-display text-xl font-semibold text-navy-900">Continue to the {{ \App\Support\Brand::name() }} app</h1>
    <p class="mt-1 text-center text-sm text-ink-400">Signed in as {{ auth()->user()->email }}</p>

    <p class="mt-6 text-sm text-ink-600">The app on this phone will be signed in to your account, with the same access you have on the website. You can sign it out at any time from the app or by changing your password.</p>

    <div class="mt-6 grid grid-cols-2 gap-3">
        <form method="POST" action="{{ route('mobile.authorize.deny') }}">
            @csrf
            @foreach ($params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <x-ui.button variant="secondary" class="w-full justify-center">Cancel</x-ui.button>
        </form>
        <form method="POST" action="{{ route('mobile.authorize.approve') }}">
            @csrf
            @foreach ($params as $key => $value)
                <input type="hidden" name="{{ $key }}" value="{{ $value }}">
            @endforeach
            <x-ui.button class="w-full justify-center">Continue</x-ui.button>
        </form>
    </div>

    <form method="POST" action="{{ route('logout') }}" class="mt-6 text-center">
        @csrf
        <button type="submit" class="text-xs font-medium text-brand-600">Not you? Use a different account</button>
    </form>
@endsection
