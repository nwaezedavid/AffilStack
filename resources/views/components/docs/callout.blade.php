@props(['type' => 'info', 'title' => null, 'big' => false])
@php
    $icon = match ($type) {
        'tip' => 'heroicon-o-light-bulb',
        'warning' => 'heroicon-o-exclamation-triangle',
        'danger' => 'heroicon-o-shield-exclamation',
        'cost' => 'heroicon-o-currency-dollar',
        default => 'heroicon-o-information-circle',
    };
@endphp
<div class="afs-g-callout afs-g-callout--{{ $type }} {{ $big ? 'afs-g-callout--big' : '' }}" role="note">
    <x-filament::icon :icon="$icon" />
    <div>
        @if ($title)
            <span class="afs-g-callout-title">{{ $title }}</span>
        @endif
        {{ $slot }}
    </div>
</div>
