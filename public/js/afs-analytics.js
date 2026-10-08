/*!
 * AffilStack first-party analytics client (agent 04).
 *
 * Waits for the cookie choice from afs-consent.js, then:
 * - analytics allowed: records the page view against the anonymous visitor
 *   id, measures engaged (visible) time and scroll depth, tracks CTA clicks
 *   (any element with data-track-cta, plus links to pricing/plan pages),
 *   outbound links, and custom events from window.affilstackTrack();
 * - not allowed: sends one anonymous aggregate page-view count (no id) and
 *   drops every queued event.
 *
 * If the visitor accepts analytics while on this page, the anonymous count
 * already sent for it is upgraded in place (never counted twice).
 *
 * Public API:
 *   window.affilstackTrack('event', { name: 'webinar_signup', topic: 'tiktok' })
 *   window.affilstackTrack('event', 'webinar_signup', { topic: 'tiktok' })
 */
(function () {
    'use strict';

    var cfg = window.AFS_ANALYTICS;
    if (!cfg || window.__afsAnalyticsLoaded) { return; }
    window.__afsAnalyticsLoaded = true;

    var pending = (window.affilstackTrack && window.affilstackTrack.q) ? window.affilstackTrack.q.slice() : [];
    var consent = null;
    var viewToken = null;
    var sentFull = false;
    var sentAny = false;
    var reported = false;
    var visibleMs = 0;
    var visibleSince = document.visibilityState === 'visible' ? Date.now() : null;
    var maxScroll = 0;

    function post(url, body) {
        try {
            return fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(body),
                keepalive: true,
                credentials: 'same-origin'
            }).then(function (r) { return r.ok ? r.json() : null; }).catch(function () { return null; });
        } catch (e) {
            return Promise.resolve(null);
        }
    }

    function beacon(url, body) {
        var payload = JSON.stringify(body);
        if (navigator.sendBeacon) {
            try {
                if (navigator.sendBeacon(url, new Blob([payload], { type: 'application/json' }))) { return; }
            } catch (e) {}
        }
        post(url, body);
    }

    function sendView(upgrade) {
        var body = {
            path: location.pathname,
            referrer: document.referrer || null,
            query: location.search || null,
            lang: navigator.language || null,
            vid: consent && consent.analytics ? consent.visitorId : null
        };
        if (upgrade && viewToken) { body.upgrade = viewToken; }

        post(cfg.viewUrl, body).then(function (data) {
            if (!data) { return; }
            if (data.token) { viewToken = data.token; }
            if (data.vid && window.AffilstackConsent) {
                window.AffilstackConsent.setVisitorId(data.vid);
                if (consent) { consent.visitorId = data.vid; }
            }
        });
    }

    function sendEvent(name, props, useBeacon) {
        if (!consent || !consent.analytics || !consent.visitorId) { return; }
        if (typeof name !== 'string' || !/^[a-z][a-z0-9_]{1,59}$/.test(name)) { return; }
        var body = { name: name, props: props || null, path: location.pathname, vid: consent.visitorId };
        if (useBeacon) { beacon(cfg.eventUrl, body); } else { post(cfg.eventUrl, body); }
    }

    function flush() {
        var queue = pending;
        pending = [];
        queue.forEach(function (args) { track.apply(null, args); });
    }

    function track(type, nameOrProps, maybeProps) {
        if (type !== 'event') { return; }
        var name = nameOrProps;
        var props = maybeProps;
        if (nameOrProps && typeof nameOrProps === 'object') {
            props = {};
            Object.keys(nameOrProps).forEach(function (k) { if (k !== 'name') { props[k] = nameOrProps[k]; } });
            name = nameOrProps.name;
        }
        if (!consent || !consent.decided) {
            pending.push([type, nameOrProps, maybeProps]);
            return;
        }
        sendEvent(name, props, false);
    }

    window.affilstackTrack = track;

    function onConsent(state) {
        consent = state;
        if (!sentAny) {
            sentAny = true;
            sentFull = !!state.analytics;
            sendView(false);
        } else if (state.analytics && !sentFull) {
            sentFull = true;
            sendView(true);
        }
        if (state.decided) { flush(); }
    }

    document.addEventListener('afs:consent', function (e) { onConsent(e.detail || {}); });

    // Engagement: visible time and the deepest scroll point reached.
    function measureScroll() {
        var doc = document.documentElement;
        var height = Math.max(doc.scrollHeight, document.body ? document.body.scrollHeight : 0);
        var seen = (window.scrollY || doc.scrollTop) + window.innerHeight;
        var pct = height > 0 ? Math.min(100, Math.round((seen / height) * 100)) : 100;
        if (pct > maxScroll) { maxScroll = pct; }
    }

    window.addEventListener('scroll', function () { measureScroll(); }, { passive: true });
    window.addEventListener('load', measureScroll);

    function engagedSeconds() {
        var ms = visibleMs + (visibleSince ? Date.now() - visibleSince : 0);
        return Math.round(ms / 1000);
    }

    function report() {
        if (reported || !viewToken) { return; }
        reported = true;
        beacon(cfg.durationUrl, { token: viewToken, seconds: Math.min(86400, engagedSeconds()), scroll: sentFull ? maxScroll : null });
    }

    document.addEventListener('visibilitychange', function () {
        if (document.visibilityState === 'hidden') {
            if (visibleSince) { visibleMs += Date.now() - visibleSince; visibleSince = null; }
            report();
        } else {
            visibleSince = Date.now();
        }
    });
    window.addEventListener('pagehide', function () {
        if (visibleSince) { visibleMs += Date.now() - visibleSince; visibleSince = null; }
        report();
    });

    // Automatic CTA and outbound-link tracking.
    document.addEventListener('click', function (e) {
        if (!consent || !consent.analytics) { return; }
        var el = e.target.closest ? e.target.closest('[data-track-cta], a[href]') : null;
        if (!el) { return; }

        var label = (el.getAttribute('data-track-cta') || el.getAttribute('aria-label') || el.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 80);
        var section = el.closest('[data-track-location], section[id], header, footer, nav, aside');
        var where = section ? (section.getAttribute('data-track-location') || section.id || section.tagName.toLowerCase()) : null;

        if (el.hasAttribute('data-track-cta')) {
            sendEvent('cta_click', { label: label, location: where, target: el.getAttribute('href') || null }, true);
            return;
        }

        var href = el.getAttribute('href') || '';
        if (/^(mailto:|tel:|javascript:|#)/i.test(href)) { return; }

        var url;
        try { url = new URL(href, location.href); } catch (err) { return; }

        if (url.host !== location.host) {
            sendEvent('outbound_click', { host: url.host.slice(0, 100), label: label }, true);
            return;
        }

        if (/^\/(pricing|get-started\/)/.test(url.pathname) && url.pathname !== location.pathname) {
            sendEvent('cta_click', { label: label, location: where, target: url.pathname }, true);
        }
    }, true);
})();
