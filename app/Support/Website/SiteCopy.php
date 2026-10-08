<?php

namespace App\Support\Website;

use App\Models\SiteSetting;
use App\Support\Homepage\HomepageContent;
use App\Support\SafeHtml;
use Illuminate\Support\HtmlString;

/**
 * Editable copy for the public pages outside the homepage — the Features
 * overview, FAQ, Help Centre and Pricing pages — with the shipped text as
 * the default for anything an admin leaves blank. Values are SiteSetting
 * keys (so Tony's previews and per-key caching work exactly like
 * HomepageContent). Edited under Website → Features overview / FAQ page /
 * Help Centre / Pricing page.
 */
class SiteCopy
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        $siteName = (string) SiteSetting::get('site_name', 'AffilStack');

        return [
            // Features overview (/features)
            'features_eyebrow' => 'Every tool in one platform',
            'features_heading' => 'Everything you need to *find buyers and get paid*',
            'features_subheading' => 'Research a product, find the people who need it, reach them with AI-written campaigns and content, and track every click and commission — '.$siteName.' replaces a dozen separate tools.',
            'features_primary_label' => 'See plans & pricing',
            'features_secondary_label' => 'Talk to an expert',
            'features_card_link_label' => 'Explore',
            'features_cta_heading' => 'Ready to put every tool to work?',
            'features_cta_text' => 'Pick a plan and research your first product in minutes.',
            'features_cta_label' => 'See plans & pricing',

            // Feature detail pages (shared labels)
            'feature_steps_heading' => 'How to use it',
            'feature_faq_heading' => 'Questions about this feature',
            'feature_related_heading' => 'Works great with',
            'feature_video_label' => 'Watch it in action',
            'feature_primary_label' => 'Get started',
            'feature_secondary_label' => 'See all features',

            // FAQ page (/faq)
            'faq_eyebrow' => 'FAQ',
            'faq_heading' => 'Frequently asked questions',
            'faq_subheading' => 'Straight answers about '.$siteName.' — research, buyers, campaigns, content, billing and your account.',
            'faq_search_placeholder' => 'Search the questions…',
            'faq_help_heading' => 'Still have a question?',
            'faq_help_text' => 'Our team and our AI assistant are here to help.',
            'faq_help_label' => 'Visit the Help Centre',

            // Help Centre (/help)
            'help_eyebrow' => 'Help Centre',
            'help_heading' => 'How can we *help* you today?',
            'help_subheading' => 'Get an instant answer, open a ticket or browse our guides — whichever is fastest for you.',
            'help_chat_title' => 'Chat with support',
            'help_chat_text' => 'Our AI assistant knows the platform and answers instantly. It can hand you to a human ticket any time.',
            'help_ticket_title' => 'Open a ticket',
            'help_ticket_text' => 'For account, billing or technical issues you\'d like our team to track.',
            'help_email_title' => 'Email us',
            'help_email_text' => 'For anything that isn\'t urgent.',
            'help_signin_note' => 'Chat and tickets need you to be signed in — you\'ll be asked to log in first if you aren\'t.',
            'help_resources_heading' => 'Guides & resources',
            'help_faq_title' => 'Frequently asked questions',
            'help_faq_text' => 'Quick answers about features, billing and your account.',
            'help_learn_title' => 'Learning Centre',
            'help_learn_text' => 'Free video tutorials that walk you through every feature.',
            'help_features_title' => 'Feature guides',
            'help_features_text' => 'What every tool does and how to use it, with screenshots.',
            'help_contact_title' => 'Talk to an expert',
            'help_contact_text' => 'Not a customer yet? Ask us anything before you buy.',
            'help_popular_heading' => 'Popular questions',

            // Pricing page (/pricing)
            'pricing_eyebrow' => 'Plans & pricing',
            'pricing_heading' => 'Simple pricing for *every stage* of your affiliate business',
            'pricing_subheading' => 'Pick a plan to create your account — payment happens once, and your account is ready the moment it clears.',
            'pricing_featured_label' => 'Most popular',
            'pricing_button_label' => 'Get started',
            'pricing_details_label' => 'See everything included',
            'pricing_guarantee' => '14-day money-back guarantee on every plan.',
            'pricing_footnote' => 'Payments are processed securely — the payment methods available depend on your country.',
            'pricing_faq_heading' => 'Questions before you buy?',
            'pricing_faq_text' => 'Read the FAQ or talk to an expert — we\'re happy to help you pick the right plan.',
        ];
    }

    public static function text(string $key): string
    {
        $value = SiteSetting::get($key);

        if (is_string($value) && trim($value) !== '') {
            return trim($value);
        }

        return static::defaults()[$key] ?? '';
    }

    /**
     * *Asterisk* highlighting, exactly like the homepage headings.
     */
    public static function highlight(string $key, string $class = 'text-gold-400'): HtmlString
    {
        return HomepageContent::highlight(static::text($key), $class);
    }

    public static function plain(string $key): string
    {
        return HomepageContent::plain(static::text($key));
    }

    /**
     * A site path / http(s) URL setting, or $fallback.
     */
    public static function url(string $key, string $fallback): string
    {
        $value = trim((string) SiteSetting::get($key, ''));

        return $value !== '' && SafeHtml::isSafeUrl($value) ? $value : $fallback;
    }

    /**
     * Saves form values: a value identical to its default is stored blank,
     * so the page keeps following the shipped copy until someone actually
     * changes it.
     *
     * @param  array<string, mixed>  $values
     * @param  array<string, string>  $defaults
     */
    public static function save(array $values, array $defaults): void
    {
        foreach ($values as $key => $value) {
            $value = is_string($value) ? trim($value) : $value;

            if (is_string($value) && array_key_exists($key, $defaults) && $value === trim($defaults[$key])) {
                $value = '';
            }

            SiteSetting::set($key, $value);
        }
    }
}
