@extends('layouts.developer')

@section('title', 'New add-on')
@section('noindex', '1')

@section('content')
    <div class="mx-auto max-w-3xl">
        <a href="{{ route('developer.dashboard') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> Your add-ons</a>
        <h1 class="mt-3 font-display text-2xl font-semibold text-navy-900">New add-on</h1>

        <form method="POST" action="{{ route('developer.addons.store') }}" class="mt-6 space-y-6">
            @csrf
            <fieldset>
                <legend class="text-sm font-medium text-navy-900">What are you building?</legend>
                <div class="mt-3 grid gap-3 sm:grid-cols-3">
                    @foreach ($types as $key => $type)
                        <label class="relative flex cursor-pointer flex-col rounded-2xl border border-line bg-surface p-4 has-[:checked]:border-brand-500 has-[:checked]:ring-2 has-[:checked]:ring-brand-500/30">
                            <input type="radio" name="type" value="{{ $key }}" class="sr-only" @checked(old('type', 'app') === $key)>
                            <span class="font-semibold text-navy-900">{{ $type['label'] }}</span>
                            <span class="mt-1 text-xs text-ink-600">{{ $type['description'] }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <x-ui.field label="Name" for="name" hint="You can change it later." :error="$errors->first('name')">
                <input id="name" name="name" value="{{ old('name') }}" required maxlength="60" class="w-full rounded-xl border border-line bg-white px-3.5 py-2.5 text-sm text-navy-900 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
            </x-ui.field>
            <x-ui.button icon="arrow-right">Create and continue</x-ui.button>
        </form>
    </div>
@endsection
