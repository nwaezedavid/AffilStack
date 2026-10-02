{{--
    First-party analytics client (item #4 statistics area, extended by agent
    04) — shared by the marketing and dashboard layouts so both logged-out
    and logged-in traffic are measured the same way. Fires client-side
    rather than in server middleware so a CDN/browser cache in front of the
    app never causes an undercount.

    It waits for the visitor's cookie choice (public/js/afs-consent.js) and
    then either records the page against their anonymous visitor id
    (analytics consent: sessions, attribution, engagement, events) or, with
    no consent, only an anonymous aggregate count. window.affilstackTrack()
    is available immediately; calls made before consent are queued and are
    dropped if analytics is refused. See public/js/afs-analytics.js.
--}}
@php
    $afsAnalyticsConfig = [
        'viewUrl' => route('analytics.beacon.view', absolute: false),
        'durationUrl' => route('analytics.beacon.duration', absolute: false),
        'eventUrl' => route('analytics.beacon.event', absolute: false),
    ];
@endphp
<script>
    window.affilstackTrack = window.affilstackTrack || function () { (window.affilstackTrack.q = window.affilstackTrack.q || []).push(arguments); };
    window.AFS_ANALYTICS = @json($afsAnalyticsConfig);
</script>
<script src="{{ asset('js/afs-analytics.js') }}?v={{ @filemtime(public_path('js/afs-analytics.js')) ?: '1' }}" defer></script>
