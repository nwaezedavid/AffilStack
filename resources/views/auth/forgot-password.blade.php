@extends('layouts.guest')

@section('title', 'Reset your password')

@section('content')
    <h2 class="font-display font-semibold text-xl text-navy-900 mb-1">Forgot your password?</h2>
    <p class="text-sm text-ink-600 mb-6">We'll email you a link to reset it.</p>

    <form method="POST" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium text-ink-900 mb-1">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus
                   class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
        </div>
        <button type="submit" class="w-full rounded-md bg-navy-900 text-white text-sm font-medium py-2.5 hover:bg-navy-800 transition">
            Email reset link
        </button>
    </form>
@endsection
