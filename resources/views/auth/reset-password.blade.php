@extends('layouts.guest')

@section('title', 'Set a new password')

@section('content')
    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Set a new password</h2>

    <form method="POST" action="{{ route('password.update') }}" class="space-y-4 mt-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-medium text-ink-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email', $request->email) }}" required autofocus
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium text-ink-900 mb-1">New password</label>
            <input id="password" type="password" name="password" required
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium text-ink-900 mb-1">Confirm new password</label>
            <input id="password_confirmation" type="password" name="password_confirmation" required
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
            Reset password
        </button>
    </form>
@endsection
