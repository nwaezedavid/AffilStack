{{--
    Sitewide social icon row — reads the same seo_social_* SiteSetting keys
    the SEO Settings admin page already writes (Site > SEO Settings >
    Social profiles), which previously fed only the invisible Organization
    JSON-LD sameAs array. This is the one place those URLs turn into
    visible icons, so an admin who fills in a profile URL there sees it
    appear everywhere this component is used (the site footer, the Contact
    page) without any separate "social links" field to maintain.

    A platform with no URL entered is simply omitted — never a dead/empty
    icon. Renders nothing at all if none are set.

    Props:
      variant: 'default' (muted icons for a light background — the footer)
               or 'inverted' (light icons for a dark background)
--}}
@props(['variant' => 'default'])

@php
    $platforms = [
        'facebook' => ['url' => \App\Models\SiteSetting::get('seo_social_facebook'), 'label' => 'Facebook'],
        'twitter' => ['url' => \App\Models\SiteSetting::get('seo_social_twitter'), 'label' => 'X (Twitter)'],
        'linkedin' => ['url' => \App\Models\SiteSetting::get('seo_social_linkedin'), 'label' => 'LinkedIn'],
        'youtube' => ['url' => \App\Models\SiteSetting::get('seo_social_youtube'), 'label' => 'YouTube'],
        'instagram' => ['url' => \App\Models\SiteSetting::get('seo_social_instagram'), 'label' => 'Instagram'],
    ];

    $platforms = array_filter($platforms, fn ($platform) => filled($platform['url']));

    $anchorClass = $variant === 'inverted'
        ? 'text-white/60 hover:text-white transition'
        : 'text-ink-400 hover:text-navy-900 transition';
@endphp

@if (! empty($platforms))
    <div {{ $attributes->class(['flex items-center gap-4']) }}>
        @foreach ($platforms as $key => $platform)
            <a
                href="{{ $platform['url'] }}"
                target="_blank"
                rel="noopener noreferrer"
                aria-label="{{ $platform['label'] }}"
                class="{{ $anchorClass }}"
            >
                <span class="sr-only">{{ $platform['label'] }}</span>
                @switch($key)
                    @case('facebook')
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M22 12.06C22 6.48 17.52 2 11.94 2S1.88 6.48 1.88 12.06c0 5.02 3.66 9.18 8.44 9.94v-7.03H7.9v-2.91h2.42V9.91c0-2.39 1.42-3.71 3.6-3.71 1.04 0 2.13.19 2.13.19v2.34h-1.2c-1.18 0-1.55.73-1.55 1.48v1.78h2.64l-.42 2.91h-2.22V22c4.78-.76 8.44-4.92 8.44-9.94Z"/></svg>
                        @break
                    @case('twitter')
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M18.24 3H21l-6.6 7.54L22 21h-6.2l-4.86-6.36L5.1 21H2.3l7.06-8.06L2 3h6.34l4.4 5.83L18.24 3Zm-1.09 16.2h1.53L7 4.7H5.36l11.79 14.5Z"/></svg>
                        @break
                    @case('linkedin')
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M4.98 3.5a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5ZM3 9.98h4v10.02H3V9.98Zm7 0h3.83v1.37h.05c.53-1 1.83-2.06 3.77-2.06 4.03 0 4.77 2.65 4.77 6.1v6.6h-4v-5.85c0-1.4-.03-3.2-1.95-3.2-1.96 0-2.26 1.53-2.26 3.1v5.94h-4V9.98Z"/></svg>
                        @break
                    @case('youtube')
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31 31 0 0 0 0 12a31 31 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31 31 0 0 0 24 12a31 31 0 0 0-.5-5.8ZM9.6 15.5v-7l6.3 3.5-6.3 3.5Z"/></svg>
                        @break
                    @case('instagram')
                        <svg viewBox="0 0 24 24" class="h-5 w-5" fill="currentColor" aria-hidden="true"><path d="M12 2c2.7 0 3.06.01 4.12.06 1.06.05 1.79.22 2.43.47.66.26 1.22.6 1.77 1.15.55.55.89 1.11 1.15 1.77.25.64.42 1.37.47 2.43.05 1.06.06 1.42.06 4.12s-.01 3.06-.06 4.12c-.05 1.06-.22 1.79-.47 2.43a4.9 4.9 0 0 1-1.15 1.77 4.9 4.9 0 0 1-1.77 1.15c-.64.25-1.37.42-2.43.47-1.06.05-1.42.06-4.12.06s-3.06-.01-4.12-.06c-1.06-.05-1.79-.22-2.43-.47a4.9 4.9 0 0 1-1.77-1.15 4.9 4.9 0 0 1-1.15-1.77c-.25-.64-.42-1.37-.47-2.43C2.01 15.06 2 14.7 2 12s.01-3.06.06-4.12c.05-1.06.22-1.79.47-2.43.26-.66.6-1.22 1.15-1.77a4.9 4.9 0 0 1 1.77-1.15c.64-.25 1.37-.42 2.43-.47C8.94 2.01 9.3 2 12 2Zm0 1.8c-2.65 0-2.98.01-4.03.06-.86.04-1.33.18-1.64.3-.41.16-.71.35-1.02.66-.31.31-.5.61-.66 1.02-.12.31-.26.78-.3 1.64C4.3 8.72 4.29 9.05 4.29 12s.01 3.28.06 4.32c.04.86.18 1.33.3 1.64.16.41.35.71.66 1.02.31.31.61.5 1.02.66.31.12.78.26 1.64.3 1.05.05 1.38.06 4.03.06s2.98-.01 4.03-.06c.86-.04 1.33-.18 1.64-.3.41-.16.71-.35 1.02-.66.31-.31.5-.61.66-1.02.12-.31.26-.78.3-1.64.05-1.04.06-1.37.06-4.32s-.01-3.28-.06-4.32c-.04-.86-.18-1.33-.3-1.64a2.74 2.74 0 0 0-.66-1.02 2.74 2.74 0 0 0-1.02-.66c-.31-.12-.78-.26-1.64-.3C14.98 3.81 14.65 3.8 12 3.8Zm0 3.05a5.15 5.15 0 1 1 0 10.3 5.15 5.15 0 0 1 0-10.3Zm0 1.8a3.35 3.35 0 1 0 0 6.7 3.35 3.35 0 0 0 0-6.7Zm5.35-2a1.2 1.2 0 1 1 0 2.4 1.2 1.2 0 0 1 0-2.4Z"/></svg>
                        @break
                @endswitch
            </a>
        @endforeach
    </div>
@endif
