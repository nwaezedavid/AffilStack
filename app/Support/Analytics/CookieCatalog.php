<?php

namespace App\Support\Analytics;

use App\Models\GoogleSiteAnalyticsSetting;
use App\Models\SiteSetting;

/**
 * Agent 04: the single list of every cookie the site can set, grouped by
 * consent category — rendered in the preferences modal, the Cookie Policy
 * page and the admin's "what is collected" panel, so all three always
 * match what the site actually does. Third-party tags only appear once the
 * admin has configured them (SEO settings / Site Analytics).
 */
class CookieCatalog
{
    /**
     * @return array<string, array{label: string, description: string, required: bool, cookies: array<int, array{name: string, provider: string, purpose: string, duration: string}>}>
     */
    public static function categories(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');
        $hasGoogle = filled(SiteSetting::get('seo_ga_id')) || filled(GoogleSiteAnalyticsSetting::cachedGtmPublicId());
        $hasMeta = filled(SiteSetting::get('seo_meta_pixel_id'));
        $hasTikTok = filled(SiteSetting::get('seo_tiktok_pixel_id'));
        $consentDays = (int) config('analytics.cookies.consent_days');

        $necessary = [
            ['name' => (string) config('session.cookie'), 'provider' => $siteName, 'purpose' => 'Keeps you signed in and remembers your session while you move between pages.', 'duration' => 'Until you close the browser (or '.(int) config('session.lifetime').' minutes of inactivity)'],
            ['name' => 'XSRF-TOKEN', 'provider' => $siteName, 'purpose' => 'Security: protects forms against cross-site request forgery.', 'duration' => 'Session'],
            ['name' => (string) config('analytics.cookies.consent'), 'provider' => $siteName, 'purpose' => 'Remembers the cookie choices you made in this banner, so we don\'t ask on every page.', 'duration' => $consentDays.' days'],
            ['name' => 'remember_web_*', 'provider' => $siteName, 'purpose' => 'Only if you tick "Remember me" when signing in: keeps you signed in on this device.', 'duration' => '5 years or until you sign out'],
            ['name' => (string) config('referrals.cookie_name'), 'provider' => $siteName, 'purpose' => 'Only if you arrived through a partner\'s referral link: credits that partner if you subscribe. Contains the partner\'s code, nothing about you.', 'duration' => (int) config('referrals.cookie_days').' days'],
        ];

        $analytics = [
            ['name' => (string) config('analytics.cookies.visitor'), 'provider' => $siteName.' (first-party)', 'purpose' => 'A random, anonymous id so we can tell new visits from returning ones, group page views into visits and see which pages and features are useful. Never shared with anyone.', 'duration' => '13 months'],
        ];

        if ($hasGoogle) {
            $analytics[] = ['name' => '_ga, _ga_*', 'provider' => 'Google Analytics', 'purpose' => 'Google Analytics measures visits and page views on our behalf (with Google Consent Mode v2).', 'duration' => 'Up to 2 years'];
        }

        $marketing = [];

        if ($hasGoogle) {
            $marketing[] = ['name' => '_gcl_au, _gcl_*', 'provider' => 'Google Ads (via Google Tag Manager)', 'purpose' => 'Measures which Google ads led to a signup and lets us show relevant ads.', 'duration' => '90 days'];
        }

        if ($hasMeta) {
            $marketing[] = ['name' => '_fbp, _fbc', 'provider' => 'Meta (Facebook & Instagram) Pixel', 'purpose' => 'Measures which Facebook/Instagram ads brought visitors and signups, and builds audiences for our ads on Meta.', 'duration' => '90 days'];
        }

        if ($hasTikTok) {
            $marketing[] = ['name' => '_ttp, _tt_*', 'provider' => 'TikTok Pixel', 'purpose' => 'Measures which TikTok ads brought visitors and signups, and builds audiences for our ads on TikTok.', 'duration' => '13 months'];
        }

        return [
            'necessary' => [
                'label' => 'Strictly necessary',
                'description' => 'Needed for the site to work — signing in, security, and remembering your cookie choice. These can\'t be switched off.',
                'required' => true,
                'cookies' => $necessary,
            ],
            'analytics' => [
                'label' => 'Analytics',
                'description' => 'Help us understand how our pages and dashboard are used — which pages people visit, where they came from, which features they use — so we can improve them. Only measured on '.$siteName.' itself, never on other websites.',
                'required' => false,
                'cookies' => $analytics,
            ],
            'marketing' => [
                'label' => 'Marketing & advertising',
                'description' => 'Let advertising partners measure our ads and show you more relevant ones. If you allow this, a hashed (scrambled) version of your email may be shared with ad platforms to include or exclude you from our ad audiences.',
                'required' => false,
                'cookies' => $marketing,
            ],
        ];
    }
}
