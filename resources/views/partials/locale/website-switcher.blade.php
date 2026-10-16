{{-- Language links for the public website: each goes to this page's address in that language (see LocalizedUrls). Variants: header, drawer, footer. --}}
@php
    $localeOptions = \App\Support\Translation\Languages::switcherOptions();
    $currentLocale = app()->getLocale();
    $variant = $variant ?? 'header';
@endphp

@if (count($localeOptions) > 1)
    @if ($variant === 'header')
        <details class="relative hidden lg:block" data-locale-switcher translate="no">
            <summary class="inline-flex cursor-pointer list-none items-center gap-1 rounded-full px-2.5 py-2 text-sm font-medium text-ink-600 transition hover:bg-surface-muted hover:text-navy-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 [&::-webkit-details-marker]:hidden" aria-label="Language: {{ $localeOptions[$currentLocale] ?? 'English' }}">
                <x-heroicon-o-language class="h-4 w-4 text-brand-600" aria-hidden="true" />
                <span class="uppercase">{{ $currentLocale }}</span>
                <x-heroicon-m-chevron-down class="h-3.5 w-3.5" aria-hidden="true" />
            </summary>
            <ul class="absolute right-0 z-50 mt-2 w-44 rounded-xl border border-line bg-white p-1 shadow-lg" role="list">
                @foreach ($localeOptions as $code => $label)
                    <li>
                        <a href="{{ \App\Support\Translation\LocalizedUrls::current(request(), $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" data-locale-link
                           @if ($code === $currentLocale) aria-current="true" @endif
                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm {{ $code === $currentLocale ? 'bg-brand-50 font-semibold text-brand-700' : 'text-navy-900 hover:bg-surface-muted' }}">
                            {{ $label }}
                            @if ($code === $currentLocale)<x-heroicon-m-check class="h-4 w-4" aria-hidden="true" />@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </details>
    @else
        <nav aria-label="Language" translate="no" class="{{ $variant === 'footer' ? 'flex flex-wrap items-center gap-x-3 gap-y-1' : 'flex flex-wrap items-center justify-center gap-2 pt-3' }}">
            <x-heroicon-o-language class="h-4 w-4 {{ $variant === 'footer' ? 'text-white/50' : 'text-ink-400' }}" aria-hidden="true" />
            @foreach ($localeOptions as $code => $label)
                <a href="{{ \App\Support\Translation\LocalizedUrls::current(request(), $code) }}" hreflang="{{ $code }}" lang="{{ $code }}" data-locale-link
                   @if ($code === $currentLocale) aria-current="true" @endif
                   class="{{ $variant === 'footer'
                        ? ($code === $currentLocale ? 'font-semibold text-white' : 'text-white/60 hover:text-white')
                        : ($code === $currentLocale ? 'rounded-full bg-navy-900 px-3 py-1 text-xs font-semibold text-white' : 'rounded-full px-3 py-1 text-xs font-medium text-ink-600 ring-1 ring-line hover:bg-white') }}">{{ $label }}</a>
            @endforeach
        </nav>
    @endif
@endif
