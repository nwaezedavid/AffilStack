@extends('layouts.marketing')

<?php $supportEmail = \App\Models\SiteSetting::get('support_email'); ?>

@section('title', 'Contact us — '.\App\Models\SiteSetting::get('site_name', 'AffilStack'))
@section('meta_description', 'Get in touch with the '.\App\Models\SiteSetting::get('site_name', 'AffilStack').' team — questions, support, or partnership inquiries.')

@section('content')
    <section class="max-w-3xl mx-auto px-6 py-16">
        <div class="text-center mb-10">
            <h1 class="font-display font-semibold text-3xl sm:text-4xl text-navy-900 text-wrap-balance">Contact us</h1>
            <p class="text-ink-600 mt-3">
                Have a question before you sign up, or need help with your account? Send us a message and we'll reply by email.
                @if ($supportEmail)
                    You can also reach us directly at <a href="mailto:{{ $supportEmail }}" class="text-brand-600 underline">{{ $supportEmail }}</a>.
                @endif
            </p>
        </div>

        <form method="POST" action="{{ route('contact.store') }}" class="border border-line rounded-lg p-6 sm:p-8 space-y-5">
            @csrf

            <div class="grid sm:grid-cols-2 gap-5">
                <div>
                    <label for="name" class="block text-sm font-medium text-navy-900 mb-1">Name</label>
                    <input type="text" name="name" id="name" value="{{ old('name') }}" required
                        class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-navy-900 mb-1">Email</label>
                    <input type="email" name="email" id="email" value="{{ old('email') }}" required
                        class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                    @error('email')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label for="subject" class="block text-sm font-medium text-navy-900 mb-1">Subject (optional)</label>
                <input type="text" name="subject" id="subject" value="{{ old('subject') }}"
                    class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">
                @error('subject')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <div>
                <label for="message" class="block text-sm font-medium text-navy-900 mb-1">Message</label>
                <textarea name="message" id="message" rows="6" required
                    class="w-full rounded-md border border-line px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500">{{ old('message') }}</textarea>
                @error('message')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
            </div>

            <button type="submit" class="w-full sm:w-auto rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition">
                Send message
            </button>
        </form>
    </section>
@endsection
