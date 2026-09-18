@extends('layouts.marketing')

<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $helloEmail = \App\Models\SiteSetting::get('contact_email_hello', 'hello@affilstack.com');
    $infoEmail = \App\Models\SiteSetting::get('contact_email_info', 'info@affilstack.com');
    $supportEmail = \App\Models\SiteSetting::get('support_email', 'support@affilstack.com');
    $contactImage = \App\Models\SiteSetting::get('contact_image_path');

    $emailChannels = array_filter([
        ['label' => 'General inquiries', 'email' => $helloEmail, 'icon' => '💬'],
        ['label' => 'Sales & partnerships', 'email' => $infoEmail, 'icon' => '💼'],
        ['label' => 'Support', 'email' => $supportEmail, 'icon' => '🛟'],
    ], fn ($channel) => filled($channel['email']));
?>

@section('title', 'Contact us — '.$siteName)
@section('meta_description', 'Get in touch with the '.$siteName.' team by email, social media, or our contact form — questions, support, or partnership inquiries.')

@section('content')
    <section class="max-w-6xl mx-auto px-6 py-16">
        <div class="text-center max-w-2xl mx-auto mb-12">
            <h1 class="font-display font-semibold text-3xl sm:text-4xl text-navy-900 text-wrap-balance">Contact us</h1>
            <p class="text-ink-600 mt-3">We'd love to hear from you. Reach us by email, social media, or the form below — we typically reply within one business day.</p>
        </div>

        <div class="grid lg:grid-cols-2 gap-10 items-start">
            {{-- Left: image + direct channels --}}
            <div class="space-y-8">
                @if ($contactImage)
                    <img
                        src="{{ \Illuminate\Support\Facades\Storage::disk('public')->url($contactImage) }}"
                        alt="{{ $siteName }} team"
                        class="w-full rounded-xl border border-line shadow-sm object-cover aspect-[4/3]"
                    >
                @else
                    {{-- No admin-uploaded image yet (Site > Contact Page) — a
                         branded placeholder instead of an empty box. --}}
                    <div class="w-full aspect-[4/3] rounded-xl bg-gradient-to-br from-navy-900 to-navy-700 flex flex-col items-center justify-center gap-3 text-center px-6 relative overflow-hidden">
                        <div class="absolute inset-0 opacity-[0.07]" style="background-image: radial-gradient(circle, white 1px, transparent 1px); background-size: 20px 20px;" aria-hidden="true"></div>
                        <span class="relative h-14 w-14 rounded-full bg-white/10 flex items-center justify-center text-white text-2xl" aria-hidden="true">✉️</span>
                        <p class="relative text-white/70 text-sm max-w-xs">We're a real team behind {{ $siteName }} — happy to help however we can.</p>
                    </div>
                @endif

                @if (! empty($emailChannels))
                    <div class="grid sm:grid-cols-2 gap-3">
                        @foreach ($emailChannels as $channel)
                            <a href="mailto:{{ $channel['email'] }}" class="border border-line rounded-lg p-4 bg-surface hover:border-brand-500 hover:shadow-sm transition group">
                                <div class="text-xl mb-2" aria-hidden="true">{{ $channel['icon'] }}</div>
                                <div class="text-xs font-medium text-navy-900">{{ $channel['label'] }}</div>
                                <div class="text-xs text-ink-600 group-hover:text-brand-600 break-all mt-0.5">{{ $channel['email'] }}</div>
                            </a>
                        @endforeach
                    </div>
                @endif

                <div>
                    <div class="text-xs font-semibold uppercase tracking-wide text-ink-400 mb-3">Follow us</div>
                    <x-social-links />
                </div>
            </div>

            {{-- Right: contact form --}}
            <form method="POST" action="{{ route('contact.store') }}" class="border border-line rounded-lg p-6 sm:p-8 space-y-5 bg-surface">
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

                <button type="submit" class="w-full rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition">
                    Send message
                </button>
                <p class="text-xs text-ink-400 text-center">We'll reply by email — usually within one business day.</p>
            </form>
        </div>
    </section>
@endsection
