{{--
    First-party page-view beacon (item #4, statistics area) — shared by the
    marketing and dashboard layouts so both logged-out and logged-in traffic
    are counted the same way. Fires client-side rather than in server
    middleware so a CDN/browser cache in front of the app in production
    never causes an undercount. Records the visit on load, then reports how
    long the visitor stayed via sendBeacon on page hide (the one reliable
    place to catch a tab close/navigation-away — a normal fetch there can
    be cancelled mid-flight by the browser).
--}}
<script>
    (function () {
        try {
            var start = Date.now();
            var viewToken = null;

            fetch('{{ route('analytics.beacon.view') }}', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify({
                    path: window.location.pathname,
                    referrer: document.referrer || null,
                    query: window.location.search || null,
                }),
                keepalive: true,
            })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (data) { if (data && data.token) { viewToken = data.token; } })
                .catch(function () {});

            var reportDuration = function () {
                if (! viewToken) { return; }
                var seconds = Math.round((Date.now() - start) / 1000);
                var payload = JSON.stringify({ token: viewToken, seconds: seconds });

                if (navigator.sendBeacon) {
                    navigator.sendBeacon(
                        '{{ route('analytics.beacon.duration') }}',
                        new Blob([payload], { type: 'application/json' })
                    );
                } else {
                    fetch('{{ route('analytics.beacon.duration') }}', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: payload,
                        keepalive: true,
                    }).catch(function () {});
                }

                viewToken = null;
            };

            document.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden') { reportDuration(); }
            });
            window.addEventListener('pagehide', reportDuration);
        } catch (e) {}
    })();
</script>
