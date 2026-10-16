@extends('layouts.marketing')

@section('title', $version->name.' · AffilStack Marketplace')
@section('meta_description', $version->tagline)

@section('content')
    <section class="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8 lg:py-14">
        <a href="{{ route('marketplace.index') }}" class="inline-flex items-center gap-1 text-sm font-medium text-brand-600 hover:text-brand-700"><x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> Marketplace</a>

        <div class="mt-6 grid gap-8 lg:grid-cols-3">
            <div class="lg:col-span-2">
                <div class="flex items-start gap-4">
                    @include('partials.marketplace.addon-icon', ['version' => $version, 'size' => 'h-16 w-16 text-lg'])
                    <div class="min-w-0">
                        <h1 class="font-display text-3xl font-semibold tracking-tight text-navy-900">{{ $version->name }}</h1>
                        <p class="mt-1 text-sm text-ink-400">by {{ $addon->developer->display_name }} · {{ $addon->typeLabel() }} · {{ $version->categoryLabel() }}</p>
                    </div>
                </div>
                <p class="mt-5 text-lg text-ink-600">{{ $version->tagline }}</p>

                @if ($version->screenshotUrls() !== [])
                    <div class="mt-6 flex snap-x gap-3 overflow-x-auto pb-2">
                        @foreach ($version->screenshotUrls() as $url)
                            <img src="{{ $url }}" alt="Screenshot of {{ $version->name }}" class="h-56 w-auto shrink-0 snap-start rounded-xl border border-line object-cover" loading="lazy">
                        @endforeach
                    </div>
                @endif

                <div class="mt-8">
                    <h2 class="font-display text-lg font-semibold text-navy-900">About this add-on</h2>
                    <div class="mt-3">@include('partials.marketplace.description', ['text' => $version->description])</div>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="rounded-2xl border border-line bg-surface p-5 shadow-sm">
                    <p class="font-display text-2xl font-semibold text-navy-900">{{ $version->priceLabel() }}</p>
                    @if ($version->isPaid())
                        <p class="mt-1 text-xs text-ink-400">Billed monthly by AffilStack. Cancel any time.</p>
                    @endif
                    <a href="{{ route('marketplace.addon', $addon) }}" class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-navy-900 px-4 py-3 text-sm font-semibold text-white hover:bg-navy-800">
                        {{ $addon->isApp() ? 'Connect to AffilStack' : 'Add to AffilStack' }}
                    </a>
                    <p class="mt-2 text-center text-xs text-ink-400">You'll sign in to your AffilStack account.</p>
                </div>

                @if ($addon->isApp() && $version->scopeDescriptions() !== [])
                    <div class="rounded-2xl border border-line bg-surface p-5">
                        <p class="text-sm font-semibold text-navy-900">What it can access</p>
                        <ul class="mt-3 space-y-2 text-sm text-ink-600">
                            @foreach ($version->scopeDescriptions() as $description)
                                <li class="flex gap-2"><x-heroicon-m-check class="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" aria-hidden="true" /> {{ $description }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-3 text-xs text-ink-400">Only with your permission, and you can disconnect at any time.</p>
                    </div>
                @endif

                <div class="rounded-2xl border border-line bg-surface p-5 text-sm">
                    <dl class="space-y-2">
                        <div class="flex justify-between gap-3"><dt class="text-ink-400">Version</dt><dd class="text-navy-900">{{ $version->version }}</dd></div>
                        <div class="flex justify-between gap-3"><dt class="text-ink-400">Updated</dt><dd class="text-navy-900">{{ $version->reviewed_at?->format('M j, Y') }}</dd></div>
                    </dl>
                    <div class="mt-4 space-y-1.5">
                        @foreach (['website_url' => 'Website', 'support_url' => 'Support', 'privacy_url' => 'Privacy policy'] as $field => $label)
                            @if ($version->{$field})
                                <a href="{{ $version->{$field} }}" target="_blank" rel="noopener nofollow" class="flex items-center gap-1.5 text-brand-600 hover:text-brand-700">{{ $label }} <x-heroicon-m-arrow-top-right-on-square class="h-3.5 w-3.5" aria-hidden="true" /></a>
                            @endif
                        @endforeach
                    </div>
                </div>
                <p class="px-1 text-xs text-ink-400">Built by an independent developer and reviewed by AffilStack.</p>
            </aside>
        </div>
    </section>
@endsection
