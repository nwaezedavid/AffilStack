{{-- One labelled image spot on the public site: exactly where it appears,
     what's showing there right now (an upload or the shipped screenshot),
     the size to make, and what the picture should show. Inline styles: the
     admin panel uses Filament's precompiled CSS, not the site's Tailwind. --}}
@php
    $uploaded = is_string($current ?? null) && $current !== '' ? $current : null;
    $src = \App\Models\FeaturePage::imageUrl($uploaded, $default ?? null);
@endphp
<div style="display: flex; gap: 1rem; align-items: flex-start; flex-wrap: wrap; padding: 0.875rem; border-radius: 0.75rem; background: rgba(36,82,217,0.05); border: 1px solid rgba(36,82,217,0.18);">
    <div style="width: 12rem; flex-shrink: 0;">
        @if ($src)
            <a href="{{ $src }}" target="_blank" rel="noopener" title="Open full size">
                <img src="{{ $src }}" alt="" style="display: block; width: 100%; aspect-ratio: 16 / 10; object-fit: cover; object-position: top; border-radius: 0.5rem; border: 1px solid rgba(0,0,0,0.1); background: #fff;">
            </a>
        @else
            <div style="width: 100%; aspect-ratio: 16 / 10; border-radius: 0.5rem; border: 1px dashed rgba(107,114,128,0.5); display: flex; align-items: center; justify-content: center; font-size: 0.75rem; color: #6b7280; text-align: center; padding: 0.5rem;">No image yet</div>
        @endif
    </div>
    <div style="flex: 1; min-width: 14rem; font-size: 0.8125rem; line-height: 1.45;">
        <p style="font-family: ui-monospace, monospace; font-size: 0.6875rem; letter-spacing: 0.06em; text-transform: uppercase; color: #1b3fb3; font-weight: 600;">Appears on: {{ $where }}</p>
        <p style="margin-top: 0.35rem;">
            @if ($uploaded)
                <strong>Showing your uploaded image.</strong> Remove it below to go back to the built-in screenshot.
            @elseif ($src)
                <strong>Showing the built-in screenshot</strong> (shipped with AffilStack — no upload needed). Upload below only if you want to replace it.
            @else
                <strong>Nothing is showing here yet.</strong> Upload an image below.
            @endif
        </p>
        <p style="margin-top: 0.35rem; color: #4b5563;"><strong>Recommended size:</strong> {{ $size }}</p>
        @if (! empty($shows))
            <p style="margin-top: 0.25rem; color: #4b5563;"><strong>What it should show:</strong> {{ $shows }}</p>
        @endif
    </div>
</div>
