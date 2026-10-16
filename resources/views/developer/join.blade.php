@extends('layouts.developer')

@section('title', 'Become a developer')
@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-2xl">
        <h1 class="font-display text-2xl font-semibold text-navy-900">Become an AffilStack developer</h1>
        <p class="mt-2 text-sm text-ink-600">Signed in as {{ auth()->user()->email }}. Tell customers who you are, accept the terms, and you can create your first add-on.</p>

        <form method="POST" action="{{ route('developer.join.store') }}" class="mt-6 space-y-5 rounded-2xl border border-line bg-surface p-6">
            @csrf
            @include('developer.partials.profile-fields')

            <div>
                <p class="text-sm font-medium text-navy-900">Developer terms</p>
                <div class="mt-2 max-h-64 overflow-y-auto whitespace-pre-line rounded-xl border border-line bg-surface-muted p-4 text-sm text-ink-600">{{ $terms }}</div>
                <label class="mt-3 flex items-start gap-2 text-sm text-navy-900">
                    <input type="checkbox" name="accept_terms" value="1" class="mt-0.5 rounded border-line" @checked(old('accept_terms'))>
                    <span>I accept the developer terms, including the {{ rtrim(rtrim(number_format($share, 2), '0'), '.') }}% revenue share.</span>
                </label>
                @error('accept_terms')<p class="mt-1 text-xs text-red-600">{{ \App\Support\Translation\Mark::text($message) }}</p>@enderror
            </div>

            <x-ui.button icon="check">Create my developer account</x-ui.button>
        </form>
    </div>
@endsection
