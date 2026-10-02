@props(['store', 'url' => null, 'size' => 'md'])

{{-- An official-style store download badge ("GET IT ON Google Play" /
     "Download on the App Store"), drawn in HTML + SVG with the brand marks
     from config/brand_icons.php so it stays crisp and needs no image file.
     With no $url it renders a muted "Coming soon" badge instead of a link.
     See App\Support\MobileApps. --}}
@php
    $isGoogle = $store === 'google';
    $icon = config('brand_icons.'.($isGoogle ? 'googleplay' : 'apple'));
    $overline = $isGoogle ? 'GET IT ON' : 'Download on the';
    $storeName = $isGoogle ? 'Google Play' : 'App Store';
    $appName = \App\Models\SiteSetting::get('site_name', 'AffilStack');
    $sizes = [
        'sm' => ['box' => 'gap-2 px-3 py-1.5 rounded-lg', 'icon' => 'h-5 w-5', 'over' => 'text-[9px]', 'name' => 'text-sm'],
        'md' => ['box' => 'gap-2.5 px-4 py-2 rounded-xl', 'icon' => 'h-7 w-7', 'over' => 'text-[10px]', 'name' => 'text-lg'],
    ];
    $s = $sizes[$size] ?? $sizes['md'];
    $gradientId = 'gp-'.\Illuminate\Support\Str::random(6);
    $classes = 'inline-flex items-center bg-black text-white ring-1 ring-white/25 shadow-sm transition '.$s['box'];
@endphp

@if ($url)
    <a href="{{ $url }}" target="_blank" rel="noopener" {{ $attributes->merge(['class' => $classes.' hover:bg-neutral-800 hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-gold-400 focus-visible:ring-offset-2 focus-visible:ring-offset-navy-950']) }}
       aria-label="{{ $isGoogle ? 'Get '.$appName.' on Google Play' : 'Download '.$appName.' on the App Store' }}">
@else
    <span {{ $attributes->merge(['class' => $classes.' opacity-80 cursor-default']) }} aria-label="{{ $appName }} on {{ $storeName }} — coming soon">
@endif
    <svg viewBox="0 0 24 24" class="{{ $s['icon'] }} shrink-0" aria-hidden="true">
        @if ($isGoogle)
            <defs>
                <linearGradient id="{{ $gradientId }}" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0" stop-color="#00D7FE" />
                    <stop offset="0.35" stop-color="#00F076" />
                    <stop offset="0.65" stop-color="#FFC900" />
                    <stop offset="1" stop-color="#FF3A44" />
                </linearGradient>
            </defs>
            <path fill="url(#{{ $gradientId }})" d="{{ $icon['path'] }}" />
        @else
            <path fill="#ffffff" d="{{ $icon['path'] }}" />
        @endif
    </svg>
    <span class="flex flex-col leading-none text-left" aria-hidden="true">
        <span class="{{ $s['over'] }} font-medium tracking-wide {{ $isGoogle ? 'uppercase' : '' }}">{{ $url ? $overline : 'Coming soon to' }}</span>
        <span class="{{ $s['name'] }} font-semibold -mt-0.5 tracking-tight" style="font-family: -apple-system, 'Segoe UI', Roboto, 'IBM Plex Sans', sans-serif;">{{ $storeName }}</span>
    </span>
@if ($url)
    </a>
@else
    </span>
@endif
