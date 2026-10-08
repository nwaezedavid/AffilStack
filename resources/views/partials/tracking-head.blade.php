{{--
    Sitewide analytics/ad-pixel <head> scripts, shared by the marketing and
    dashboard layouts so tracking covers logged-out visitors and logged-in
    customer behavior alike. Expects $gtmPublicId, $gaId, $metaPixelId,
    $tiktokPixelId to already be in scope (set by the including layout).

    Agent 04 — consent gating (NDPA 2023, GDPR/ePrivacy, US state laws):
    nothing optional runs before the visitor agrees. Google Consent Mode v2
    starts with every storage type denied; each third-party loader below is
    an inert <script type="text/plain" data-afs-consent="…"> that the cookie
    banner (partials/consent/banner + public/js/afs-consent.js) turns into a
    real script only after the matching consent — "google" after analytics
    OR marketing consent (Consent Mode tells Google which), "marketing" for
    the Meta and TikTok pixels. Pages are cached, so this HTML is identical
    for everyone and the decision happens in the browser; no <noscript>
    pixel fallbacks, since without script there is no way to consent.
--}}
@php
    $afsConsentVersion = \App\Support\Analytics\ConsentSettings::policyVersion();
    $afsGoogleMode = \App\Support\Analytics\ConsentSettings::googleMode();
    $afsGoogleGate = $afsGoogleMode === 'advanced' ? 'text/javascript' : 'text/plain';
@endphp
@if ($gtmPublicId || $gaId)
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('consent', 'default', {
            ad_storage: 'denied', ad_user_data: 'denied', ad_personalization: 'denied',
            analytics_storage: 'denied', personalization_storage: 'denied',
            functionality_storage: 'granted', security_storage: 'granted',
            wait_for_update: 500
        });
        gtag('set', 'ads_data_redaction', true);
        (function () {
            try {
                var m = document.cookie.match(/(?:^|; )afs_consent=([^;]*)/);
                if (!m) { return; }
                var c = decodeURIComponent(m[1]);
                var v = /(?:^|\|)v:([^|]*)/.exec(c);
                if (!v || v[1] !== @json($afsConsentVersion)) { return; }
                var a = /\|a:1(\||$)/.test(c), k = /\|m:1(\||$)/.test(c);
                gtag('consent', 'update', {
                    analytics_storage: a ? 'granted' : 'denied',
                    ad_storage: k ? 'granted' : 'denied',
                    ad_user_data: k ? 'granted' : 'denied',
                    ad_personalization: k ? 'granted' : 'denied'
                });
                if (k) { gtag('set', 'ads_data_redaction', false); }
            } catch (e) {}
        })();
    </script>
@endif
@if ($gtmPublicId)
    <script type="{{ $afsGoogleGate }}" data-afs-consent="google">
        (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start': new Date().getTime(),event:'gtm.js'});
        var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';
        j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer',@json($gtmPublicId));
    </script>
@elseif ($gaId)
    <script type="{{ $afsGoogleGate }}" data-afs-consent="google">
        (function(d){var j=d.createElement('script');j.async=true;
        j.src='https://www.googletagmanager.com/gtag/js?id='+encodeURIComponent(@json((string) $gaId));
        d.head.appendChild(j);})(document);
        gtag('js', new Date());
        gtag('config', @json((string) $gaId));
    </script>
@endif
@if ($metaPixelId)
    <script type="text/plain" data-afs-consent="marketing">
        !function(f,b,e,v,n,t,s)
        {if(f.fbq)return;n=f.fbq=function(){n.callMethod?
        n.callMethod.apply(n,arguments):n.queue.push(arguments)};
        if(!f._fbq)f._fbq=n;n.push=n;n.loaded=!0;n.version='2.0';
        n.queue=[];t=b.createElement(e);t.async=!0;
        t.src=v;s=b.getElementsByTagName(e)[0];
        s.parentNode.insertBefore(t,s)}(window, document,'script',
        'https://connect.facebook.net/en_US/fbevents.js');
        fbq('init', @json((string) $metaPixelId));
        fbq('track', 'PageView');
    </script>
@endif
@if ($tiktokPixelId)
    <script type="text/plain" data-afs-consent="marketing">
        !function (w, d, t) {
          w.TiktokAnalyticsObject=t;var ttq=w[t]=w[t]||[];ttq.methods=["page","track","identify","instances","debug","on","off","once","ready","alias","group","enableCookie","disableCookie","holdConsent","revokeConsent","grantConsent"],ttq.setAndDefer=function(t,e){t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}};for(var i=0;i<ttq.methods.length;i++)ttq.setAndDefer(ttq,ttq.methods[i]);ttq.instance=function(t){for(var e=ttq._i[t]||[],n=0;n<ttq.methods.length;n++)ttq.setAndDefer(e,ttq.methods[n]);return e},ttq.load=function(e,n){var i="https://analytics.tiktok.com/i18n/pixel/events.js",o=n&&n.partner;ttq._i=ttq._i||{},ttq._i[e]=[],ttq._i[e]._u=i,ttq._t=ttq._t||{},ttq._t[e]=+new Date,ttq._o=ttq._o||{},ttq._o[e]=n||{};n=document.createElement("script");n.type="text/javascript",n.async=!0,n.src=i+"?sdkid="+e+"&lib="+t;e=document.getElementsByTagName("script")[0];e.parentNode.insertBefore(n,e)};
          ttq.load(@json((string) $tiktokPixelId));
          ttq.page();
        }(window, document, 'ttq');
    </script>
@endif
