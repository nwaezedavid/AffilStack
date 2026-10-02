{{-- A step/feature icon: a Heroicon name (e.g. "map-pin") renders the
     outline icon; anything else (older cards stored an emoji) is shown as
     text. --}}
@php
    $iconName = trim((string) ($icon ?? ''));
    $iconSvg = null;

    if ($iconName !== '' && preg_match('/^[a-z0-9-]+$/', $iconName)) {
        try {
            $iconSvg = svg('heroicon-o-'.$iconName, $class ?? 'h-6 w-6', ['aria-hidden' => 'true'])->toHtml();
        } catch (\Throwable) {
            $iconSvg = null;
        }
    }
@endphp
@if ($iconSvg)
    {!! $iconSvg !!}
@elseif ($iconName !== '')
    <span class="text-2xl leading-none" aria-hidden="true">{{ $iconName }}</span>
@else
    <x-heroicon-o-sparkles class="{{ $class ?? 'h-6 w-6' }}" aria-hidden="true" />
@endif
