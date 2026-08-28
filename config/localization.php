<?php

/**
 * Multi-market localization (Phase 3, item 8).
 *
 * "Translated, not just word-swapped" means different things depending on
 * the target market: Nigeria, Ghana, Kenya, and the diaspora markets below
 * are English-speaking, so localizing for them means rewriting currency
 * amounts, payment-method references, and cultural examples for that
 * specific audience while staying in English — a real content adaptation,
 * not a cosmetic find-and-replace. For markets whose `language` isn't
 * English, LocalizationService actually translates the content as part of
 * that same adaptation pass, rather than translating first and adapting
 * separately.
 */
return [
    'markets' => [
        'NG' => [
            'label' => 'Nigeria',
            'flag' => '🇳🇬',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'Nigerian naira (₦)',
            'context' => 'A Nigerian online audience. Reference familiar local payment habits (bank transfer, USSD codes, a Paystack/Flutterwave-style checkout) instead of assuming a US credit card is the default, price comparisons in naira rather than dollars, and everyday examples a Nigerian reader would recognize.',
        ],
        'GH' => [
            'label' => 'Ghana',
            'flag' => '🇬🇭',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'Ghanaian cedi (GH₵)',
            'context' => 'A Ghanaian online audience. Mobile money (MTN Mobile Money, Vodafone Cash) is the common online-payment reference rather than a card, price comparisons in cedis, and everyday examples a Ghanaian reader would recognize.',
        ],
        'KE' => [
            'label' => 'Kenya',
            'flag' => '🇰🇪',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'Kenyan shilling (KSh)',
            'context' => 'A Kenyan online audience. M-Pesa is the default payment-method reference rather than a card, price comparisons in shillings, and everyday examples a Kenyan reader would recognize.',
        ],
        'ZA' => [
            'label' => 'South Africa',
            'flag' => '🇿🇦',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'South African rand (R)',
            'context' => 'A South African online audience. EFT and card payments are both common, price comparisons in rand, and everyday examples a South African reader would recognize.',
        ],
        'diaspora' => [
            'label' => 'African diaspora (US/UK/Canada)',
            'flag' => '🌍',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'US dollar (USD)',
            'context' => 'An African immigrant/diaspora audience living in the US, UK, or Canada — content should feel written for someone straddling both worlds (may compare prices or send money "back home"), not a generic Western reader.',
        ],
        'US' => [
            'label' => 'United States',
            'flag' => '🇺🇸',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'US dollar (USD)',
            'context' => 'A general US audience — standard American context, pricing in dollars, familiar US retail and payment norms.',
        ],
        'GB' => [
            'label' => 'United Kingdom',
            'flag' => '🇬🇧',
            'language' => 'en',
            'language_name' => 'English',
            'currency' => 'British pound (£)',
            'context' => 'A general UK audience — British English spelling and idiom, pricing in pounds, familiar UK retail and payment norms.',
        ],
        'FR' => [
            'label' => 'France / Francophone',
            'flag' => '🇫🇷',
            'language' => 'fr',
            'language_name' => 'French',
            'currency' => 'euro (€)',
            'context' => 'A French-speaking audience. Pricing in euros, familiar French/European retail and payment norms, and examples that read naturally in French rather than a literal translation of English phrasing.',
        ],
        'ES' => [
            'label' => 'Spain / Latin America',
            'flag' => '🇪🇸',
            'language' => 'es',
            'language_name' => 'Spanish',
            'currency' => 'US dollar or euro depending on the sub-region',
            'context' => 'A Spanish-speaking audience across Spain and Latin America. Neutral, widely-understood Spanish (avoid region-specific slang), and examples that read naturally in Spanish rather than a literal translation of English phrasing.',
        ],
    ],

    // Which generated-content modules can be localized. Restricted to the
    // same finished, single-asset content types the content calendar treats
    // as "publishable" (config/calendar.php) — research, keyword lists,
    // angle ideas, and multi-recipient sequences (DM/email) aren't a single
    // asset to hand a different market, so they're excluded here too.
    'localizable_modules' => [
        'blog_article',
        'linkedin_post',
        'linkedin_article',
        'youtube_script',
        'ugc_content',
        'x_thread',
        'tiktok_video',
        'pinterest_pin',
    ],
];
