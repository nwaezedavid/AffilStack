@php
    $videoId = \App\Support\YouTubeUrl::idFrom($url ?? null);
@endphp

{{-- Inline styles: the admin panel uses Filament's precompiled CSS, not the site's Tailwind build. --}}
<div style="border: 1px dashed rgba(107,114,128,0.4); border-radius: 0.75rem; padding: 1rem;">
    <p style="font-size: 0.875rem; font-weight: 600;">Live preview</p>
    @if ($videoId)
        <p style="margin-top: 0.25rem; font-size: 0.75rem; color: #6b7280;">
            Video found (ID <code>{{ $videoId }}</code>). This is exactly what visitors will play once you save.
        </p>
        <div style="margin-top: 0.75rem; max-width: 36rem; aspect-ratio: 16 / 9; overflow: hidden; border-radius: 0.5rem; background: #000;">
            <iframe
                src="{{ \App\Support\YouTubeUrl::embedUrl($url, autoplay: false) }}"
                title="YouTube video preview"
                style="width: 100%; height: 100%; border: 0;"
                loading="lazy"
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>
    @elseif (filled($url ?? null))
        <p style="margin-top: 0.25rem; font-size: 0.875rem; color: #dc2626;">We couldn't find a video in that link. Open the video on YouTube and copy the address bar, or use Share → Copy link.</p>
    @else
        <p style="margin-top: 0.25rem; font-size: 0.75rem; color: #6b7280;">Paste a YouTube link above — the video previews here as soon as you click outside the box.</p>
    @endif
</div>
