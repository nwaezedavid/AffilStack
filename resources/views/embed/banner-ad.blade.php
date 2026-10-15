@php
    /** @var \App\Models\BannerAd $ad */
    $ad = $banner['ad'];
    $w = $banner['width'];
    $h = $banner['height'];
    $ratio = $w / max(1, $h);
    $layout = match (true) {
        $h <= 60 => 'micro',
        $w <= 360 && $h <= 120 => 'compact',
        $ratio >= 2.4 => 'wide',
        $ratio < 0.75 => 'tall',
        default => 'rect',
    };
    $dark = in_array($ad->theme, ['navy', 'blue', 'emerald'], true);
    $logo = $dark ? (\App\Support\Brand::whiteLogoUrl() ?? \App\Support\Brand::logoUrl()) : \App\Support\Brand::logoUrl();
    $icon = $dark ? (\App\Support\Brand::whiteIconUrl() ?? \App\Support\Brand::iconUrl()) : \App\Support\Brand::iconUrl();
    $siteName = \App\Support\Brand::name();
    $image = $ad->imageUrl();
    $showImage = $image && in_array($layout, ['rect', 'tall'], true) && $h >= 250;
    $endsSoon = $ad->ends_at && $ad->discount_badge ? 'Ends '.$ad->ends_at->format('M j') : null;
    $palette = [
        'navy' => ['bg' => 'linear-gradient(135deg,#0b1e3d 0%,#14305e 60%,#2452d9 140%)', 'ink' => '#ffffff', 'muted' => 'rgba(255,255,255,.78)', 'cta' => '#c9a24a', 'ctaInk' => '#0b1e3d', 'badge' => '#c9a24a', 'badgeInk' => '#0b1e3d'],
        'blue' => ['bg' => 'linear-gradient(135deg,#1e3fb8 0%,#2452d9 55%,#4f7cff 120%)', 'ink' => '#ffffff', 'muted' => 'rgba(255,255,255,.82)', 'cta' => '#ffffff', 'ctaInk' => '#1e3fb8', 'badge' => '#ffd166', 'badgeInk' => '#14223d'],
        'gold' => ['bg' => 'linear-gradient(135deg,#f6e7bf 0%,#e9cf86 60%,#c9a24a 140%)', 'ink' => '#14223d', 'muted' => 'rgba(20,34,61,.78)', 'cta' => '#0b1e3d', 'ctaInk' => '#ffffff', 'badge' => '#0b1e3d', 'badgeInk' => '#f6e7bf'],
        'emerald' => ['bg' => 'linear-gradient(135deg,#022c22 0%,#065f46 60%,#0f9f78 140%)', 'ink' => '#ffffff', 'muted' => 'rgba(236,253,245,.8)', 'cta' => '#fbbf24', 'ctaInk' => '#022c22', 'badge' => '#fbbf24', 'badgeInk' => '#022c22'],
        'light' => ['bg' => 'linear-gradient(135deg,#ffffff 0%,#f3f5fb 100%)', 'ink' => '#0b1e3d', 'muted' => '#4a5672', 'cta' => '#2452d9', 'ctaInk' => '#ffffff', 'badge' => '#e8402f', 'badgeInk' => '#ffffff'],
    ][$ad->theme] ?? null;
    $palette ??= ['bg' => '#0b1e3d', 'ink' => '#fff', 'muted' => 'rgba(255,255,255,.8)', 'cta' => '#c9a24a', 'ctaInk' => '#0b1e3d', 'badge' => '#c9a24a', 'badgeInk' => '#0b1e3d'];
    // Type scale per footprint, in px.
    $scale = match ($layout) {
        'micro' => ['head' => $w >= 320 ? 14 : 12, 'msg' => 0, 'cta' => 11, 'badge' => 10, 'logo' => 22, 'pad' => 8],
        'compact' => ['head' => 15, 'msg' => 0, 'cta' => 12, 'badge' => 10, 'logo' => 16, 'pad' => 10],
        'wide' => ['head' => $h >= 200 ? 30 : ($h >= 100 ? 19 : 17), 'msg' => $h >= 200 ? 16 : ($h >= 100 ? 12 : 12), 'cta' => $h >= 200 ? 16 : 13, 'badge' => $h >= 200 ? 15 : 11, 'logo' => $h >= 200 ? 34 : 26, 'pad' => $h >= 200 ? 28 : 12],
        'tall' => ['head' => $w >= 300 ? 28 : 19, 'msg' => $w >= 300 ? 15 : 12, 'cta' => $w >= 300 ? 15 : 12, 'badge' => $w >= 300 ? 14 : 11, 'logo' => $w >= 300 ? 30 : 22, 'pad' => $w >= 300 ? 22 : 12],
        default => ['head' => $w >= 330 ? 22 : 20, 'msg' => 13, 'cta' => 14, 'badge' => 12, 'logo' => 26, 'pad' => 16],
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $siteName }}</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; height: 100%; overflow: hidden; background: transparent; }
        body { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        a.ad { position: relative; display: flex; width: 100%; height: 100%; text-decoration: none; color: {{ $palette['ink'] }}; background: {{ $palette['bg'] }}; padding: {{ $scale['pad'] }}px; gap: {{ max(6, (int) round($scale['pad'] * 0.75)) }}px; overflow: hidden; border-radius: 6px; outline-offset: -3px; }
        a.ad::after { content: ""; position: absolute; inset: auto -20% -60% auto; width: 70%; aspect-ratio: 1; border-radius: 50%; background: radial-gradient(circle, rgba(255,255,255,.14), transparent 65%); pointer-events: none; }
        .logo { display: block; height: {{ $scale['logo'] }}px; width: auto; max-width: 100%; object-fit: contain; object-position: left center; }
        .logo-text { font-weight: 800; font-size: {{ (int) round($scale['logo'] * 0.62) }}px; letter-spacing: -.01em; line-height: {{ $scale['logo'] }}px; white-space: nowrap; }
        .head { margin: 0; font-weight: 800; font-size: {{ $scale['head'] }}px; line-height: 1.12; letter-spacing: -.015em; text-wrap: balance; }
        .msg { margin: 0; font-size: {{ $scale['msg'] }}px; line-height: 1.4; color: {{ $palette['muted'] }}; }
        .badge { display: inline-flex; align-items: center; gap: 4px; align-self: flex-start; white-space: nowrap; font-weight: 800; font-size: {{ $scale['badge'] }}px; letter-spacing: .04em; text-transform: uppercase; padding: .35em .7em; border-radius: 999px; background: {{ $palette['badge'] }}; color: {{ $palette['badgeInk'] }}; }
        .note { font-size: {{ max(10, $scale['badge'] - 1) }}px; color: {{ $palette['muted'] }}; }
        .cta { display: inline-flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap; font-weight: 700; font-size: {{ $scale['cta'] }}px; padding: .7em 1.15em; border-radius: 999px; background: {{ $palette['cta'] }}; color: {{ $palette['ctaInk'] }}; box-shadow: 0 6px 16px rgba(0,0,0,.18); transition: transform .15s ease; }
        a.ad:hover .cta, a.ad:focus-visible .cta { transform: translateY(-1px); }
        .shot { display: block; width: 100%; min-height: 0; flex: 1 1 auto; object-fit: cover; object-position: top center; border-radius: 6px; box-shadow: 0 10px 24px rgba(0,0,0,.25); }
        .col { display: flex; flex-direction: column; min-width: 0; }
        .grow { flex: 1 1 auto; }
        .row { display: flex; align-items: center; gap: 8px; min-width: 0; }
        .between { justify-content: space-between; }
        .clamp2 { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .clamp3 { display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
        .ellipsis { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        @media (prefers-reduced-motion: reduce) { .cta { transition: none; } }
    </style>
</head>
<body>
<a class="ad" href="{{ $link }}" target="_blank" rel="sponsored noopener" aria-label="{{ $ad->headline }}">
    @if ($layout === 'micro')
        <span class="row grow" style="align-items:center">
            @if ($icon)
                <img class="logo" src="{{ $icon }}" alt="{{ $siteName }}" style="height:{{ $scale['logo'] }}px;width:{{ $scale['logo'] }}px">
            @endif
            <span class="col grow" style="min-width:0">
                <span class="head ellipsis">{{ $ad->headline }}</span>
            </span>
            @if ($ad->discount_badge && $w >= 468)
                <span class="badge">{{ $ad->discount_badge }}</span>
            @endif
            <span class="cta" style="padding:.55em .9em">{{ $ad->cta_label ?: 'Get started' }}</span>
        </span>
    @elseif ($layout === 'compact')
        <span class="row grow" style="gap:10px">
            <span class="col grow" style="justify-content:center;gap:5px">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="logo-text">{{ $siteName }}</span>
                @endif
                <span class="head clamp2">{{ $ad->headline }}</span>
            </span>
            <span class="col" style="flex:0 0 auto;align-items:flex-end;justify-content:center;gap:6px">
                @if ($ad->discount_badge)
                    <span class="badge" style="align-self:flex-end">{{ $ad->discount_badge }}</span>
                @endif
                <span class="cta" style="padding:.6em .95em">{{ $ad->cta_label ?: 'Get started' }}</span>
            </span>
        </span>
    @elseif ($layout === 'wide')
        <span class="row grow" style="gap:{{ $scale['pad'] }}px">
            <span class="col" style="flex:0 0 auto;justify-content:center">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="{{ $siteName }}">
                @else
                    <span class="logo-text">{{ $siteName }}</span>
                @endif
            </span>
            <span class="col grow" style="justify-content:center;gap:{{ $h >= 200 ? 10 : 3 }}px">
                <span class="head clamp2">{{ $ad->headline }}</span>
                @if ($ad->message && $w >= 700 && ($h >= 200 || mb_strlen($ad->headline) <= 40))
                    <span class="msg {{ $h >= 200 ? 'clamp2' : 'ellipsis' }}">{{ $ad->message }}</span>
                @endif
            </span>
            @if ($ad->discount_badge)
                <span class="col" style="flex:0 0 auto;align-items:center;gap:4px">
                    <span class="badge">{{ $ad->discount_badge }}</span>
                    @if (($ad->discount_note || $endsSoon) && $h >= 90)
                        <span class="note ellipsis" style="max-width:{{ $h >= 200 ? 220 : 150 }}px">{{ $ad->discount_note ?: $endsSoon }}</span>
                    @endif
                </span>
            @endif
            <span class="cta" style="flex:0 0 auto">{{ $ad->cta_label ?: 'Get started' }} &rarr;</span>
        </span>
    @else
        <span class="col grow" style="gap:{{ $layout === 'tall' ? 14 : 9 }}px">
            <span class="row between">
                @if ($logo)
                    <img class="logo" src="{{ $logo }}" alt="{{ $siteName }}" style="max-width:{{ $ad->discount_badge ? '58%' : '100%' }}">
                @else
                    <span class="logo-text">{{ $siteName }}</span>
                @endif
                @if ($ad->discount_badge && $layout === 'rect')
                    <span class="badge">{{ $ad->discount_badge }}</span>
                @endif
            </span>
            @if ($ad->discount_badge && $layout === 'tall')
                <span class="badge">{{ $ad->discount_badge }}</span>
            @endif
            <span class="head {{ $layout === 'tall' ? '' : 'clamp3' }}">{{ $ad->headline }}</span>
            @if ($ad->message)
                <span class="msg {{ $layout === 'tall' ? 'clamp3' : 'clamp2' }}">{{ $ad->message }}</span>
            @endif
            @if ($showImage)
                <img class="shot" src="{{ $image }}" alt="">
            @else
                <span class="grow"></span>
            @endif
            <span class="col" style="gap:6px;{{ $layout === 'tall' ? 'align-items:stretch' : '' }}">
                <span class="cta">{{ $ad->cta_label ?: 'Get started' }} &rarr;</span>
                @if ($ad->discount_note || $endsSoon)
                    <span class="note ellipsis" style="text-align:{{ $layout === 'tall' ? 'center' : 'left' }}">{{ collect([$ad->discount_note, $endsSoon])->filter()->implode(' · ') }}</span>
                @endif
            </span>
        </span>
    @endif
</a>
</body>
</html>
