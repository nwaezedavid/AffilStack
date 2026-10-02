<?php

namespace App\Support\Analytics;

/**
 * Agent 04: starter copy for the Cookie Policy site page and the short
 * cookie paragraph appended to the Privacy Policy, shared by
 * SitePagesSeeder (fresh installs) and the 2026_10_03_040008 migration
 * (existing sites — only replaces the policy if an admin never edited it).
 * The live cookie table under the policy is rendered separately from
 * CookieCatalog. Like all seeded legal copy, have it reviewed by a lawyer.
 */
class CookiePolicyContent
{
    public const PRIVACY_MARKER = 'data-afs-cookie-addendum';

    public static function html(): string
    {
        return <<<'HTML'
            <p>This Cookie Policy explains which cookies and similar technologies AffilStack uses, why, and how you stay in control. It applies to our public website and to the AffilStack dashboard. It is written to meet Nigeria's Data Protection Act 2023, the EU and UK GDPR and ePrivacy rules, and US state privacy laws.</p>

            <h2>The short version</h2>
            <ul>
                <li>We only use <strong>strictly necessary</strong> cookies until you choose otherwise.</li>
                <li><strong>Analytics</strong> and <strong>advertising</strong> cookies — including the Google, Meta and TikTok tags — load only after you say yes. "Reject all" is always one click, exactly as easy as "Accept all".</li>
                <li>If your browser sends a <strong>Global Privacy Control</strong> or <strong>Do Not Track</strong> signal, we treat it as "no" to analytics and advertising.</li>
                <li>You can change your mind at any time from <a href="/cookie-policy#cookie-settings">Cookie settings</a> (also in the site footer and in your dashboard).</li>
            </ul>

            <h2>What cookies are</h2>
            <p>Cookies are small text files a website stores in your browser. Some are needed for the site to work; others help the site owner measure and improve it, or measure advertising.</p>

            <h2>What we measure — and what we can't</h2>
            <p>With your permission, our own first-party analytics records how our website and dashboard are used: the pages you visit, how you arrived (for example a search engine, a social network or an ad campaign), roughly how long you stay and how far you scroll, which buttons you click, and which dashboard features you use. We also note your device type, browser, operating system, language and approximate location (country and city, worked out from your IP address — the IP address itself is never stored).</p>
            <p>We <strong>cannot</strong> see what you do on other websites or apps, and we don't try to. Our cookies only work on AffilStack's own pages.</p>
            <p>If you don't allow analytics, we only keep an anonymous count of page views (no cookie, no identifier, nothing linked to you).</p>

            <h2>Advertising</h2>
            <p>If you allow marketing cookies, advertising partners (Google, Meta, TikTok — only those we actually use, listed below) can measure which ads led to a visit or signup. If you are a customer and allow marketing, we may also include a <strong>hashed</strong> (irreversibly scrambled) copy of your email address or phone number in audience lists we upload to those platforms — for example to stop showing you ads for a plan you already bought. Without marketing consent, you are never included.</p>

            <h2>How long we keep analytics data</h2>
            <p>Analytics data is automatically deleted after 13 months (or sooner if we shorten that period). If you delete your AffilStack account, your analytics data is deleted with it.</p>

            <h2>Your choices and rights</h2>
            <ul>
                <li>Change or withdraw your consent any time via <a href="/cookie-policy#cookie-settings">Cookie settings</a>. Withdrawing doesn't affect what happened before, but stops further collection immediately.</li>
                <li>You can also block or delete cookies in your browser settings. Blocking strictly necessary cookies will stop you from signing in.</li>
                <li>You have the right to access, correct or delete your personal data, and to object to its use — see our <a href="/privacy">Privacy Policy</a> or <a href="/contact">contact us</a>.</li>
            </ul>

            <h2>Changes to this policy</h2>
            <p>When we change how we use cookies in a meaningful way, we update this page and ask for your choice again.</p>
            HTML;
    }

    public static function privacyAddendum(): string
    {
        return '<p '.self::PRIVACY_MARKER.'>Analytics and advertising cookies are only used with your consent, which you can give, refuse or withdraw at any time from <a href="/cookie-policy#cookie-settings">Cookie settings</a>. We honour Global Privacy Control and Do Not Track signals. Our analytics only measures activity on AffilStack\'s own website and dashboard — never on other sites.</p>';
    }
}
