<?php
    $siteName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', $siteName)</title>
    @include('partials.seo.icons')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-surface-muted text-ink-900 antialiased min-h-screen flex items-center justify-center px-4 py-10">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <a href="{{ route('home') }}" class="inline-flex items-center gap-2 font-display font-semibold text-xl text-navy-900">
                <x-ui.brand-mark :label="$siteName" />
            </a>
        </div>

        <div class="bg-surface border border-line rounded-xl shadow-sm p-8">
            @if (session('status'))
                <div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded-md px-3 py-2">
                    {{ \App\Support\Translation\Mark::flash(session('status')) }}
                </div>
            @endif

            @if (session('error'))
                <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                    {{ \App\Support\Translation\Mark::flash(session('error')) }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded-md px-3 py-2">
                    <ul class="list-disc list-inside space-y-1">
                        @foreach ($errors->all() as $error)
                            <li>{{ \App\Support\Translation\Mark::text($error) }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @yield('content')
        </div>
        <div class="mt-6 flex justify-center">@include('partials.locale.switcher-form', ['tone' => 'light'])</div>
    </div>

    {{-- Agent 04: cookie consent banner + preferences (no trackers load on these pages). --}}
    @include('partials.consent.banner', ['context' => 'guest'])
</body>
</html>
