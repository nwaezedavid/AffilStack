@extends('layouts.guest')

@section('title', 'Confirm your password')

@section('content')
    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Confirm your password</h2>
    <p class="text-sm text-ink-600 mb-6">This is a sensitive action — please confirm your password to continue.</p>

    <form method="POST" action="{{ route('password.confirm.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="password" class="block text-sm font-medium text-ink-900 mb-1">Password</label>
            <input id="password" type="password" name="password" required autofocus
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
            Confirm
        </button>
    </form>
@endsection
