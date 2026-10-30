{{--
    The site logo, chosen from what was uploaded in Admin → Site → Brand
    Settings, so no area falls back to the two-letter placeholder while a
    logo exists.
    variant="light": colour logo → colour icon + name → white icon + name → initials + name.
    variant="dark":  white logo → white icon + name → colour icon + name →
                     colour logo on a white chip → initials + name.
    size: "sm" (h-7/h-8) or "md" (h-8/h-9). name=false hides the name next to an icon.
--}}
@props(['variant' => 'light', 'size' => 'md', 'name' => true, 'label' => null])

@php
    $siteName = $label ?? \App\Support\Brand::name();
    $logo = \App\Support\Brand::logoUrl();
    $icon = \App\Support\Brand::iconUrl();
    $whiteLogo = \App\Support\Brand::whiteLogoUrl();
    $whiteIcon = \App\Support\Brand::whiteIconUrl();
    $logoHeight = $size === 'sm' ? 'h-7' : 'h-8';
    $iconBox = $size === 'sm' ? 'h-8 w-8' : 'h-9 w-9';
    $initials = mb_strtoupper(mb_substr(trim($siteName) ?: 'A', 0, 2));

    if ($variant === 'dark') {
        [$fullLogo, $markIcon, $chipLogo] = [$whiteLogo, $whiteIcon ?? $icon, $whiteLogo || $whiteIcon || $icon ? null : $logo];
    } else {
        [$fullLogo, $markIcon, $chipLogo] = [$logo, $icon ?? $whiteIcon, null];
    }
@endphp

@if ($fullLogo)
    <img src="{{ $fullLogo }}" alt="{{ $siteName }}" {{ $attributes->merge(['class' => "{$logoHeight} w-auto"]) }}>
@elseif ($chipLogo)
    <span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-lg bg-white px-2 py-1']) }}>
        <img src="{{ $chipLogo }}" alt="{{ $siteName }}" class="{{ $size === 'sm' ? 'h-6' : 'h-7' }} w-auto">
    </span>
@elseif ($markIcon)
    <img src="{{ $markIcon }}" alt="" class="{{ $iconBox }} rounded-xl object-contain" aria-hidden="true">
    @if ($name)<span>{{ $siteName }}</span>@endif
@else
    <span class="inline-flex {{ $iconBox }} items-center justify-center rounded-xl bg-gradient-to-br {{ $variant === 'dark' ? 'from-brand-500 to-navy-800 ring-1 ring-white/15' : 'from-navy-900 to-brand-600' }} text-sm text-gold-300" aria-hidden="true">{{ $initials }}</span>
    @if ($name)<span>{{ $siteName }}</span>@endif
@endif
