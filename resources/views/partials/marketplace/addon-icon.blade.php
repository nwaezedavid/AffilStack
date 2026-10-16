{{-- An add-on's icon, or its initials. Needs $version; optional $size (tailwind classes). --}}
@php($size = $size ?? 'h-12 w-12 text-base')
@if ($version?->iconUrl())
    <img src="{{ $version->iconUrl() }}" alt="" class="{{ $size }} shrink-0 rounded-xl object-cover ring-1 ring-line" loading="lazy">
@else
    <span class="{{ $size }} inline-flex shrink-0 items-center justify-center rounded-xl bg-gradient-to-br from-navy-800 to-brand-600 font-display font-semibold text-white" aria-hidden="true">{{ \Illuminate\Support\Str::upper(\Illuminate\Support\Str::substr($version?->name ?? '?', 0, 2)) }}</span>
@endif
