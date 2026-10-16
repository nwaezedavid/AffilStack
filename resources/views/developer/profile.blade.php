@extends('layouts.developer')

@section('title', 'Profile')
@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="font-display text-2xl font-semibold text-navy-900">Developer profile</h1>
        <form method="POST" action="{{ route('developer.profile.update') }}" class="mt-6 space-y-5 rounded-2xl border border-line bg-surface p-6">
            @csrf
            @method('PUT')
            @include('developer.partials.profile-fields', ['developer' => $developer])
            <x-ui.button icon="check">Save profile</x-ui.button>
        </form>
        <p class="mt-4 text-xs text-ink-400">You accepted the developer terms on {{ $developer->terms_accepted_at?->format('M j, Y') }}. Your revenue share is {{ rtrim(rtrim(number_format($developer->sharePercent(), 2), '0'), '.') }}%.</p>
    </div>
@endsection
