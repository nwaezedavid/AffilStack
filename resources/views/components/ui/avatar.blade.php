@props(['user'])

@if ($user->avatarUrl())
    <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" {{ $attributes->merge(['class' => 'h-9 w-9 rounded-full object-cover ring-2 ring-white']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex h-9 w-9 items-center justify-center rounded-full bg-gradient-to-br from-brand-500 to-navy-800 text-xs font-semibold text-white ring-2 ring-white']) }} aria-hidden="true">{{ $user->initials() }}</span>
@endif
