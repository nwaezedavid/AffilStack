@props(['brand', 'label' => null, 'href' => null, 'type' => 'submit'])

{{-- A connect button styled to each provider's own sign-in branding:
     Google's white button with the four-colour G, LinkedIn's blue,
     TikTok/X black, YouTube red, and so on. Renders a link when "href" is
     given, otherwise a submit button for the surrounding form. --}}
@php
    $styles = [
        'google' => 'bg-white text-[#1f1f1f] ring-1 ring-[#747775] hover:bg-[#f8faff]',
        'gmail' => 'bg-white text-[#1f1f1f] ring-1 ring-[#747775] hover:bg-[#f8faff]',
        'youtube' => 'bg-[#FF0000] text-white hover:bg-[#d90000]',
        'linkedin' => 'bg-[#0A66C2] text-white hover:bg-[#004182]',
        'tiktok' => 'bg-black text-white hover:bg-[#1f1f1f]',
        'x' => 'bg-black text-white hover:bg-[#1f1f1f]',
        'pinterest' => 'bg-[#E60023] text-white hover:bg-[#ad081b]',
        'instagram' => 'bg-gradient-to-r from-[#F58529] via-[#DD2A7B] to-[#8134AF] text-white hover:opacity-90',
        'facebook' => 'bg-[#1877F2] text-white hover:bg-[#0c63d4]',
        'meta' => 'bg-[#0866FF] text-white hover:bg-[#0657d6]',
        'microsoft' => 'bg-white text-[#5E5E5E] ring-1 ring-[#8C8C8C] hover:bg-[#f3f3f3]',
        'mail' => 'bg-navy-900 text-white hover:bg-navy-800',
    ];
    $iconName = $brand === 'gmail' ? 'gmail' : $brand;
    $light = in_array($brand, ['google', 'gmail', 'microsoft'], true);
    $classes = 'inline-flex items-center justify-center gap-2.5 rounded-full px-4 py-2.5 text-sm font-medium shadow-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-brand-500 '.($styles[$brand] ?? $styles['mail']);
    $title = config('brand_icons.'.$brand.'.title', ucfirst($brand));
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span @class(['inline-flex h-5 w-5 items-center justify-center rounded-full', 'bg-white' => ! $light && ! in_array($brand, ['instagram', 'mail'], true)])>
            <x-ui.brand-icon :name="$iconName" class="h-4 w-4" :mono="! $light && in_array($brand, ['instagram', 'mail'], true)" />
        </span>
        <span>{{ $label ?? 'Continue with '.$title }}</span>
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        <span @class(['inline-flex h-5 w-5 items-center justify-center rounded-full', 'bg-white' => ! $light && ! in_array($brand, ['instagram', 'mail'], true)])>
            <x-ui.brand-icon :name="$iconName" class="h-4 w-4" :mono="! $light && in_array($brand, ['instagram', 'mail'], true)" />
        </span>
        <span>{{ $label ?? 'Continue with '.$title }}</span>
    </button>
@endif
