<?php

/**
 * The affiliate platforms offered in the Get Started research form's
 * "Affiliate platform" picker, grouped the way an affiliate thinks about
 * them. Reviewed October 2026 — every entry was checked to still run a
 * public affiliate/partner programme. Notably:
 *
 *  - ShareASale no longer exists as a separate network: Awin finished
 *    migrating every ShareASale programme in 2025, so it lives under Awin
 *    (its name stays a search alias so "ShareASale" still finds it).
 *  - Pepperjam merged into Partnerize (as Ascend), so it is an alias too.
 *  - Paystack and Flutterwave were left out on purpose: Paystack runs a
 *    Service Partner programme for developers/agencies and Flutterwave a
 *    business referral scheme — neither is a commission affiliate programme.
 *  - Takealot was left out: it has no public affiliate programme today.
 *
 * The stored value is always the plain `name` string (offers.affiliate_network
 * stays a free-text column), so offers created with older free-typed names
 * remain valid — the picker just shows them as "Other".
 *
 * `aliases` are extra search terms only; they are never stored.
 */
return [
    'groups' => [
        'major' => [
            'label' => 'Major affiliate networks',
            'networks' => [
                ['name' => 'Amazon Associates', 'aliases' => ['amazon influencer']],
                ['name' => 'Impact.com', 'aliases' => ['impact radius']],
                ['name' => 'CJ Affiliate', 'aliases' => ['commission junction', 'cj']],
                ['name' => 'Awin', 'aliases' => ['shareasale', 'share a sale']],
                ['name' => 'Rakuten Advertising', 'aliases' => ['rakuten linkshare', 'linkshare']],
                ['name' => 'Partnerize', 'aliases' => ['pepperjam', 'ascend']],
                ['name' => 'FlexOffers', 'aliases' => []],
                ['name' => 'Admitad', 'aliases' => []],
                ['name' => 'Webgains', 'aliases' => []],
                ['name' => 'Tradedoubler', 'aliases' => []],
                ['name' => 'Sovrn Commerce', 'aliases' => ['viglink']],
                ['name' => 'Skimlinks', 'aliases' => []],
                ['name' => 'Avantlink', 'aliases' => []],
                ['name' => 'MaxBounty', 'aliases' => ['cpa']],
                ['name' => 'Commission Factory', 'aliases' => ['australia']],
            ],
        ],
        'saas' => [
            'label' => 'SaaS & B2B partner platforms',
            'networks' => [
                ['name' => 'PartnerStack', 'aliases' => ['saas']],
                ['name' => 'FirstPromoter', 'aliases' => []],
                ['name' => 'Rewardful', 'aliases' => ['stripe affiliates']],
                ['name' => 'Tapfiliate', 'aliases' => []],
                ['name' => 'Refersion', 'aliases' => ['shopify']],
                ['name' => 'Everflow', 'aliases' => []],
                ['name' => 'Tolt', 'aliases' => []],
                ['name' => 'Post Affiliate Pro', 'aliases' => []],
                ['name' => 'Affise', 'aliases' => []],
                ['name' => 'Cellxpert', 'aliases' => ['forex', 'trading', 'igaming']],
            ],
        ],
        'digital' => [
            'label' => 'Digital products, courses & checkouts',
            'networks' => [
                ['name' => 'ClickBank', 'aliases' => []],
                ['name' => 'Digistore24', 'aliases' => ['digistore']],
                ['name' => 'JVZoo', 'aliases' => ['jv zoo']],
                ['name' => 'WarriorPlus', 'aliases' => ['warrior plus', 'w+']],
                ['name' => 'BuyGoods', 'aliases' => []],
                ['name' => 'Gumroad', 'aliases' => []],
                ['name' => 'Payhip', 'aliases' => []],
                ['name' => 'Lemon Squeezy', 'aliases' => ['lemonsqueezy']],
                ['name' => 'Teachable', 'aliases' => ['course']],
                ['name' => 'Kajabi', 'aliases' => ['course']],
                ['name' => 'Thinkific', 'aliases' => ['course']],
                ['name' => 'Podia', 'aliases' => ['course']],
                ['name' => 'Systeme.io', 'aliases' => ['systeme']],
                ['name' => 'ThriveCart', 'aliases' => ['thrive cart']],
                ['name' => 'SamCart', 'aliases' => ['sam cart']],
                ['name' => 'ClickFunnels', 'aliases' => ['click funnels']],
                ['name' => 'Stan Store', 'aliases' => ['stan']],
            ],
        ],
        'creator' => [
            'label' => 'Creator & e-commerce marketplaces',
            'networks' => [
                ['name' => 'Shopify Collabs', 'aliases' => ['shopify']],
                ['name' => 'TikTok Shop Affiliate', 'aliases' => ['tiktok']],
                ['name' => 'YouTube Shopping Affiliate', 'aliases' => ['youtube']],
                ['name' => 'Etsy Affiliate Program', 'aliases' => ['etsy']],
                ['name' => 'eBay Partner Network', 'aliases' => ['ebay', 'epn']],
                ['name' => 'Walmart Creator', 'aliases' => ['walmart']],
                ['name' => 'Target Partners', 'aliases' => ['target']],
                ['name' => 'AliExpress Portals', 'aliases' => ['aliexpress', 'alibaba']],
                ['name' => 'Temu Affiliate', 'aliases' => ['temu']],
                ['name' => 'LTK (LikeToKnow.it)', 'aliases' => ['rewardstyle', 'liketoknowit']],
                ['name' => 'ShopMy', 'aliases' => []],
            ],
        ],
        'africa' => [
            'label' => 'Africa',
            'networks' => [
                ['name' => 'Expertnaire', 'aliases' => ['nigeria', 'naira']],
                ['name' => 'Selar', 'aliases' => ['nigeria', 'naira', 'ghana', 'kenya']],
                ['name' => 'Jumia KOL / Affiliate', 'aliases' => ['jumia', 'nigeria', 'kenya']],
                ['name' => 'Konga Affiliate', 'aliases' => ['konga', 'nigeria']],
                ['name' => 'Kara Affiliate', 'aliases' => ['kara', 'nigeria']],
                ['name' => 'Travelstart Affiliate', 'aliases' => ['travelstart', 'flights']],
                ['name' => 'HostAfrica Affiliate', 'aliases' => ['hostafrica', 'hosting']],
                ['name' => 'Truehost Affiliate', 'aliases' => ['truehost', 'hosting', 'kenya']],
                ['name' => 'Qservers Affiliate', 'aliases' => ['qservers', 'hosting', 'nigeria']],
            ],
        ],
        'tools' => [
            'label' => 'Hosting, software & tool programmes',
            'networks' => [
                ['name' => 'Hostinger Affiliate', 'aliases' => ['hostinger', 'hosting']],
                ['name' => 'Bluehost Affiliate', 'aliases' => ['bluehost', 'hosting']],
                ['name' => 'SiteGround Affiliate', 'aliases' => ['siteground', 'hosting']],
                ['name' => 'Namecheap Affiliate', 'aliases' => ['namecheap', 'domains']],
                ['name' => 'Fiverr Affiliates', 'aliases' => ['fiverr']],
                ['name' => 'Canva (Canvassador)', 'aliases' => ['canva']],
                ['name' => 'Semrush Affiliate', 'aliases' => ['semrush', 'seo']],
                ['name' => 'HubSpot Affiliate', 'aliases' => ['hubspot', 'crm']],
                ['name' => 'Shopify Affiliate', 'aliases' => ['shopify']],
                ['name' => 'Wix Affiliate', 'aliases' => ['wix']],
                ['name' => 'Elementor Affiliate', 'aliases' => ['elementor', 'wordpress']],
                ['name' => 'GetResponse Affiliate', 'aliases' => ['getresponse', 'email']],
                ['name' => 'NordVPN Affiliate', 'aliases' => ['nordvpn', 'vpn']],
            ],
        ],
        'other' => [
            'label' => 'Something else',
            'networks' => [
                ['name' => 'Direct / in-house programme', 'aliases' => ['direct', 'in-house', 'own programme', 'brand']],
            ],
        ],
    ],

    /**
     * The picker's "Other (type the name)" choice. Submitting it requires
     * the free-text `affiliate_network_other` field, whose value is what
     * actually gets stored.
     */
    'other_value' => '__other',
];
