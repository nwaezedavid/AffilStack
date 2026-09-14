@extends('layouts.marketing')

@section('title', (\App\Models\SiteSetting::get('site_name', 'AffilStack')).' — The all-in-one platform for affiliate marketers')

@section('content')
    <section class="max-w-5xl mx-auto px-6 py-20 text-center">
        <h1 class="font-display font-semibold text-4xl sm:text-5xl text-navy-900 leading-tight max-w-3xl mx-auto text-wrap-balance">
            Find the offer. Find the buyer. Promote it everywhere — <span class="text-gold-600">in one click.</span>
        </h1>
        <p class="text-ink-600 mt-5 max-w-xl mx-auto">
            AffilStack turns a product name and an affiliate link into a research brief, a blog article,
            and a full LinkedIn campaign — built for beginners, sharp enough for pros.
        </p>
        <div class="mt-8 flex items-center justify-center gap-3">
            <a href="{{ route('registration.pricing') }}" class="rounded-md bg-navy-900 text-white px-6 py-3 text-sm font-medium hover:bg-navy-800 transition">See plans &amp; pricing</a>
        </div>
        <p class="text-xs text-ink-400 mt-4">14-day money-back guarantee on every plan.</p>
    </section>

    <section class="max-w-5xl mx-auto px-6 pb-20 grid sm:grid-cols-3 gap-6 text-sm">
        <div class="border border-line rounded-lg p-5">
            <div class="font-display font-semibold text-navy-900 mb-1">Offer research</div>
            <p class="text-ink-600">Give it a product, a URL, and an affiliate network — get your ideal buyer and best channel back in seconds.</p>
        </div>
        <div class="border border-line rounded-lg p-5">
            <div class="font-display font-semibold text-navy-900 mb-1">Content on autopilot</div>
            <p class="text-ink-600">SEO blog articles and full LinkedIn campaigns generated from that same research — no blank page.</p>
        </div>
        <div class="border border-line rounded-lg p-5">
            <div class="font-display font-semibold text-navy-900 mb-1">Built-in CRM</div>
            <p class="text-ink-600">Every lead you find lives in one place, exportable to any email platform you already use.</p>
        </div>
    </section>
@endsection
