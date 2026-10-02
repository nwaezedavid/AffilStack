/*!
 * AffilStack cookie consent manager (agent 04).
 *
 * - Shows the banner until the visitor chooses (equal-weight Accept all /
 *   Reject all / Customise) and re-opens the preferences from any
 *   [data-cookie-settings] element or a link to "#cookie-settings".
 * - Stores the choice in the first-party, strictly-necessary afs_consent
 *   cookie ("v:<policy version>|a:0|m:0|id:<uuid>|t:<unix>"); a new policy
 *   version from the admin makes the old choice stale and asks again.
 * - Honours Global Privacy Control and Do Not Track: optional cookies stay
 *   off and the banner is not shown (the visitor can still opt in).
 * - Logs every choice server-side as proof of consent.
 * - Activates the inert <script type="text/plain" data-afs-consent> tags
 *   (partials/tracking-head) only for granted categories, and keeps Google
 *   Consent Mode v2 in sync.
 * - Creates the anonymous analytics visitor id (afs_vid) only after
 *   analytics consent; deletes it and known analytics/ad cookies on refusal.
 *
 * Exposes window.AffilstackConsent { get(), onChange(fn), open(), setVisitorId(id) }
 * and dispatches a "afs:consent" DOM event with the current state.
 */
(function () {
    'use strict';

    var cfg = window.AFS_CONSENT || {};
    var CONSENT_COOKIE = cfg.cookie || 'afs_consent';
    var VISITOR_COOKIE = cfg.visitorCookie || 'afs_vid';
    var listeners = [];
    var state = null;
    var decided = false;
    var lastFocus = null;

    var banner = document.getElementById('afs-consent-banner');
    var modal = document.getElementById('afs-consent-modal');

    function readCookie(name) {
        var m = document.cookie.match(new RegExp('(?:^|; )' + name.replace(/([.$?*|{}()[\]\\/+^])/g, '\\$1') + '=([^;]*)'));
        return m ? decodeURIComponent(m[1]) : null;
    }

    function writeCookie(name, value, days) {
        var expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + expires + '; path=/; SameSite=Lax' + (location.protocol === 'https:' ? '; Secure' : '');
    }

    function deleteCookie(name) {
        var host = location.hostname;
        var parts = host.split('.');
        var domains = ['', host, '.' + host];
        if (parts.length > 2) {
            domains.push('.' + parts.slice(-2).join('.'));
        }
        domains.forEach(function (domain) {
            document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + (domain ? '; domain=' + domain : '');
        });
    }

    function deleteMatching(patterns) {
        document.cookie.split(';').forEach(function (pair) {
            var name = pair.split('=')[0].trim();
            if (patterns.some(function (p) { return p.test(name); })) {
                deleteCookie(name);
            }
        });
    }

    function uuid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        var bytes = new Uint8Array(16);
        (window.crypto || window.msCrypto).getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        var hex = Array.prototype.map.call(bytes, function (b) { return ('0' + b.toString(16)).slice(-2); }).join('');
        return hex.slice(0, 8) + '-' + hex.slice(8, 12) + '-' + hex.slice(12, 16) + '-' + hex.slice(16, 20) + '-' + hex.slice(20);
    }

    function parse(raw) {
        if (!raw) { return null; }
        var out = {};
        raw.split('|').forEach(function (pair) {
            var i = pair.indexOf(':');
            if (i > 0) { out[pair.slice(0, i)] = pair.slice(i + 1); }
        });
        if (!out.v || !out.id) { return null; }
        return { v: out.v, a: out.a === '1', m: out.m === '1', id: out.id, t: parseInt(out.t || '0', 10) };
    }

    function serialize(s) {
        return 'v:' + s.v + '|a:' + (s.a ? 1 : 0) + '|m:' + (s.m ? 1 : 0) + '|id:' + s.id + '|t:' + s.t;
    }

    function privacySignal() {
        if (navigator.globalPrivacyControl === true) { return 'gpc'; }
        if (navigator.doNotTrack === '1' || window.doNotTrack === '1' || navigator.msDoNotTrack === '1') { return 'dnt'; }
        return null;
    }

    function visitorId() {
        return state && state.a ? readCookie(VISITOR_COOKIE) : null;
    }

    function ensureVisitorId() {
        var id = readCookie(VISITOR_COOKIE);
        if (!id || !/^[0-9a-f-]{36}$/i.test(id)) {
            id = uuid();
        }
        // Re-written on every page so it expires 13 months after the LAST
        // visit, never the first.
        writeCookie(VISITOR_COOKIE, id, cfg.visitorDays || 395);
        return id;
    }

    function publicState() {
        return {
            decided: decided,
            analytics: !!(state && state.a),
            marketing: !!(state && state.m),
            visitorId: visitorId(),
            signal: privacySignal()
        };
    }

    function updateGoogle(s) {
        if (typeof window.gtag !== 'function') { return; }
        window.gtag('consent', 'update', {
            analytics_storage: s.a ? 'granted' : 'denied',
            ad_storage: s.m ? 'granted' : 'denied',
            ad_user_data: s.m ? 'granted' : 'denied',
            ad_personalization: s.m ? 'granted' : 'denied'
        });
        window.gtag('set', 'ads_data_redaction', !s.m);
    }

    function activate(category) {
        var nodes = document.querySelectorAll('script[type="text/plain"][data-afs-consent]');
        Array.prototype.forEach.call(nodes, function (el) {
            var categories = (el.getAttribute('data-afs-consent') || '').split(/\s+/);
            if (categories.indexOf(category) === -1 || el.getAttribute('data-afs-active') === '1') { return; }
            el.setAttribute('data-afs-active', '1');
            var script = document.createElement('script');
            if (el.getAttribute('data-src')) {
                script.src = el.getAttribute('data-src');
                script.async = true;
            } else {
                script.text = el.textContent;
            }
            el.parentNode.insertBefore(script, el.nextSibling);
        });
    }

    function emit() {
        var detail = publicState();
        listeners.forEach(function (fn) { try { fn(detail); } catch (e) {} });
        try { document.dispatchEvent(new CustomEvent('afs:consent', { detail: detail })); } catch (e) {}
    }

    function apply() {
        if (state && state.a) { ensureVisitorId(); }
        if (state) { updateGoogle(state); }
        if (state && (state.a || state.m)) { activate('google'); }
        if (state && state.a) { activate('analytics'); }
        if (state && state.m) { activate('marketing'); }
        emit();
    }

    function log(action, source) {
        if (!cfg.logUrl) { return; }
        var body = JSON.stringify({
            consent_id: state.id,
            analytics: state.a,
            marketing: state.m,
            action: action,
            source: source,
            policy_version: state.v,
            vid: visitorId(),
            path: location.pathname,
            gpc: navigator.globalPrivacyControl === true
        });
        try {
            fetch(cfg.logUrl, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' }, body: body, keepalive: true, credentials: 'same-origin' }).catch(function () {});
        } catch (e) {}
    }

    function save(analytics, marketing, action, source) {
        var previous = state && state.v === cfg.version ? state : null;
        var revoked = !!(previous && ((previous.a && !analytics) || (previous.m && !marketing)));

        state = {
            v: cfg.version,
            a: !!analytics,
            m: !!marketing,
            id: (state && state.id && /^[0-9a-f-]{36}$/i.test(state.id)) ? state.id : uuid(),
            t: Math.floor(Date.now() / 1000)
        };
        decided = true;
        writeCookie(CONSENT_COOKIE, serialize(state), cfg.days || 180);

        if (state.a) { ensureVisitorId(); }
        if (!state.a) { deleteCookie(VISITOR_COOKIE); deleteMatching([/^_ga($|_)/, /^_gid$/, /^_gat/]); }
        if (!state.m) { deleteMatching([/^_fbp$/, /^_fbc$/, /^_gcl_/, /^_ttp$/, /^_tt_/, /^_uet/]); }

        log(action, source);
        hideBanner();
        closeModal();
        syncToggles();

        // Scripts that already ran can't be unloaded — reload so a
        // withdrawal takes effect immediately on this page too.
        if (revoked) {
            setTimeout(function () { location.reload(); }, 250);
            return;
        }

        apply();
    }

    function showBanner() {
        if (banner) { banner.hidden = false; banner.setAttribute('data-open', ''); }
    }

    function hideBanner() {
        if (banner) { banner.hidden = true; banner.removeAttribute('data-open'); }
    }

    function toggles() {
        return modal ? modal.querySelectorAll('[data-afs-toggle]') : [];
    }

    function syncToggles() {
        Array.prototype.forEach.call(toggles(), function (el) {
            var key = el.getAttribute('data-afs-toggle');
            var on = key === 'analytics' ? !!(state && state.a) : !!(state && state.m);
            el.setAttribute('aria-checked', on ? 'true' : 'false');
        });
    }

    function toggleValue(key) {
        var el = modal ? modal.querySelector('[data-afs-toggle="' + key + '"]') : null;
        return !!(el && el.getAttribute('aria-checked') === 'true');
    }

    function focusables() {
        return modal ? Array.prototype.filter.call(modal.querySelectorAll('button, a[href], input, [tabindex]:not([tabindex="-1"]), summary'), function (el) {
            return !el.disabled && el.offsetParent !== null;
        }) : [];
    }

    function openModal() {
        if (!modal) { return; }
        lastFocus = document.activeElement;
        syncToggles();
        var notice = modal.querySelector('[data-afs-signal]');
        if (notice) { notice.hidden = !privacySignal(); }
        modal.hidden = false;
        document.documentElement.style.overflow = 'hidden';
        var first = modal.querySelector('[data-afs-initial-focus]') || focusables()[0];
        if (first) { setTimeout(function () { first.focus(); }, 10); }
    }

    function closeModal() {
        if (!modal || modal.hidden) { return; }
        modal.hidden = true;
        document.documentElement.style.overflow = '';
        if (lastFocus && typeof lastFocus.focus === 'function') { lastFocus.focus(); }
    }

    function bind() {
        document.addEventListener('click', function (e) {
            var target = e.target.closest ? e.target.closest('[data-afs-action], [data-afs-toggle], [data-cookie-settings], a[href$="#cookie-settings"]') : null;
            if (!target) { return; }

            if (target.hasAttribute('data-afs-toggle')) {
                e.preventDefault();
                target.setAttribute('aria-checked', target.getAttribute('aria-checked') === 'true' ? 'false' : 'true');
                return;
            }

            if (target.hasAttribute('data-cookie-settings') || /#cookie-settings$/.test(target.getAttribute('href') || '')) {
                e.preventDefault();
                openModal();
                return;
            }

            var action = target.getAttribute('data-afs-action');
            var source = modal && modal.contains(target) ? 'preferences' : 'banner';
            e.preventDefault();

            if (action === 'accept') { save(true, true, 'accept_all', source); }
            if (action === 'reject') {
                var hadConsent = !!(state && decided && (state.a || state.m));
                save(false, false, hadConsent ? 'withdraw' : 'reject_all', source);
            }
            if (action === 'save') {
                var a = toggleValue('analytics');
                var m = toggleValue('marketing');
                var withdrew = !!(state && decided && ((state.a && !a) || (state.m && !m))) && !a && !m;
                save(a, m, withdrew ? 'withdraw' : 'custom', 'preferences');
            }
            if (action === 'customise') { openModal(); }
            if (action === 'close') { closeModal(); }
        });

        document.addEventListener('keydown', function (e) {
            if (!modal || modal.hidden) { return; }
            if (e.key === 'Escape') { e.preventDefault(); closeModal(); return; }
            if (e.key === 'Tab') {
                var items = focusables();
                if (!items.length) { return; }
                var first = items[0];
                var last = items[items.length - 1];
                if (e.shiftKey && document.activeElement === first) { e.preventDefault(); last.focus(); }
                else if (!e.shiftKey && document.activeElement === last) { e.preventDefault(); first.focus(); }
            }
        });

        if (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target === modal || e.target.hasAttribute('data-afs-backdrop')) { closeModal(); }
            });
        }
    }

    function init() {
        bind();

        var stored = parse(readCookie(CONSENT_COOKIE));
        if (stored && stored.v === cfg.version) {
            state = stored;
            decided = true;
            apply();
            return;
        }

        // Keep the anonymous consent id across a policy-version bump so the
        // consent log shows one browser's history as one thread.
        state = stored ? { v: cfg.version, a: false, m: false, id: stored.id, t: 0 } : null;

        if (!cfg.enabled) {
            emit();
            return;
        }

        var signal = privacySignal();
        if (signal) {
            save(false, false, 'signal', 'signal');
            return;
        }

        showBanner();
        emit();
    }

    window.AffilstackConsent = {
        get: publicState,
        onChange: function (fn) {
            if (typeof fn === 'function') { listeners.push(fn); }
        },
        open: openModal,
        setVisitorId: function (id) {
            if (state && state.a && /^[0-9a-f-]{36}$/i.test(id)) { writeCookie(VISITOR_COOKIE, id, cfg.visitorDays || 395); }
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
