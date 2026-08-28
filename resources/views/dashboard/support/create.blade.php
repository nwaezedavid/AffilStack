@extends('layouts.app')

@section('title', 'Open a support ticket')

@section('content')
    <div class="max-w-xl">
        <a href="{{ route('support.index') }}" class="text-sm text-ink-400 hover:text-ink-900">&larr; Back to support</a>

        <div class="bg-surface border border-line rounded-lg p-6 mt-4">
            <h1 class="text-lg font-semibold text-ink-900 mb-1">Open a support ticket</h1>
            <p class="text-sm text-ink-600 mb-6">Prefer an instant answer? Try the <a href="{{ route('support.chat') }}" class="text-brand-600 underline">AI assistant</a> first — it can open a ticket for you too if it can't help.</p>

            <form method="POST" action="{{ route('support.store') }}" class="space-y-4">
                @csrf

                <div>
                    <label class="block text-sm font-medium text-ink-900 mb-1">Subject</label>
                    <input type="text" name="subject" value="{{ old('subject') }}" required maxlength="255"
                        class="w-full rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm">
                    @error('subject') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-ink-900 mb-1">Category</label>
                        <select name="category" class="w-full rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm">
                            @foreach (['billing' => 'Billing', 'technical' => 'Technical', 'feature_request' => 'Feature request', 'other' => 'Other'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('category') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-ink-900 mb-1">Priority</label>
                        <select name="priority" class="w-full rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm">
                            @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'] as $value => $label)
                                <option value="{{ $value }}" @selected(old('priority', 'normal') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-ink-900 mb-1">Tell us what's going on</label>
                    <textarea name="message" rows="6" required maxlength="5000"
                        class="w-full rounded-md border-line focus:border-brand-500 focus:ring-brand-500 text-sm">{{ old('message') }}</textarea>
                    @error('message') <p class="text-xs text-red-600 mt-1">{{ $message }}</p> @enderror
                </div>

                <button type="submit" class="rounded-md bg-navy-900 text-white text-sm font-medium px-4 py-2 hover:bg-navy-800 transition">
                    Submit ticket
                </button>
            </form>
        </div>
    </div>
@endsection
