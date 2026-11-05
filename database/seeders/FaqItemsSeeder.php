<?php

namespace Database\Seeders;

use App\Models\FaqItem;
use Illuminate\Database\Seeder;

/**
 * Starter knowledge base so the public /help page and the AI support chat
 * aren't empty on a fresh install. Admins edit/replace these from
 * Admin → Support → FAQ.
 */
class FaqItemsSeeder extends Seeder
{
    /**
     * Answers for features 4–7, also added to existing sites by the
     * 2026_10_22_202615 migration.
     *
     * @var array<int, array{category: string, question: string, answer: string}>
     */
    public const NEW_FEATURE_FAQS = [
        [
            'category' => 'features',
            'question' => 'Can AffilStack publish articles to my WordPress site?',
            'answer' => 'Yes. Go to WordPress Sites in your dashboard, download the free AffilStack Connector plugin, install it in WordPress and click "Connect to AffilStack". Articles arrive as drafts with images, SEO fields, categories, tags, FAQ schema and tracked links — nothing is published until you press Publish. The number of sites you can connect depends on your plan.',
        ],
        [
            'category' => 'features',
            'question' => 'Which affiliate networks can I connect?',
            'answer' => 'Impact, PartnerStack, Awin and CJ connect with their API: AffilStack brings in your programs, makes tracked links and imports your commissions into Earnings every day. ClickBank and Digistore24 have no program API, so you add a product by its ID and AffilStack builds the tracked link. Open Affiliate Networks in your dashboard to connect one. The number of connections depends on your plan.',
        ],
        [
            'category' => 'features',
            'question' => 'Can I use AffilStack in another language?',
            'answer' => 'Yes. Pick a language from the menu at the bottom of the dashboard sidebar and the whole app switches — menus, pages and emails. Your products, contacts and generated content are never translated. To rewrite content itself for another market, use Localization.',
        ],
        [
            'category' => 'features',
            'question' => 'What is the Add-on Marketplace?',
            'answer' => 'A place to add extra tools to your account: connected apps, template and prompt packs, and automation recipes for Zapier, Make and n8n, made by independent developers and reviewed by AffilStack before they go live. Many are free; paid add-ons are a monthly subscription you can cancel at any time from My add-ons.',
        ],
        [
            'category' => 'features',
            'question' => 'Can I build and sell add-ons?',
            'answer' => 'Yes. Open the developer portal (the "Build an add-on" link on the Add-on Marketplace page) and sign in with your AffilStack account. Create a listing, submit it for review, and once it is approved it appears in the marketplace. You keep 70% of every paid subscription, paid out the same way as affiliate commissions.',
        ],
    ];

    public function run(): void
    {
        $items = [
            [
                'category' => 'account',
                'question' => 'How do I create an AffilStack account?',
                'answer' => "There's no separate sign-up step — pick a plan on the Pricing page, complete payment, and your account is created automatically the moment payment is confirmed. There's no free trial account without a paid plan.",
            ],
            [
                'category' => 'account',
                'question' => 'I forgot my password. How do I reset it?',
                'answer' => 'Go to the sign-in page and click "Forgot your password?" — we\'ll email you a secure reset link.',
            ],
            [
                'category' => 'billing',
                'question' => 'How do I upgrade, downgrade, or cancel my plan?',
                'answer' => 'Go to Billing & Plan in your dashboard. You can switch plans or cancel at any time — changes take effect at your next billing cycle.',
            ],
            [
                'category' => 'billing',
                'question' => 'What payment methods do you accept?',
                'answer' => 'Payments are processed securely through Flutterwave, which supports major debit/credit cards and a range of local payment methods depending on your country.',
            ],
            [
                'category' => 'billing',
                'question' => 'Do you offer refunds?',
                'answer' => "Yes — automatically. If it's been less than 48 hours since your payment and you haven't used any AI credits yet, a \"Request refund\" button on your dashboard's billing page refunds you instantly, no support ticket needed. See our Refund & Cancellation Policy for details.",
            ],
            [
                'category' => 'features',
                'question' => 'What are credits and how do they work?',
                'answer' => 'Credits are what AffilStack uses to meter AI usage — offer research, blog articles, and LinkedIn content each consume a set number of credits per generation. Your plan includes a monthly credit allowance, visible in your dashboard sidebar.',
            ],
            [
                'category' => 'features',
                'question' => 'How long does offer research or content generation take?',
                'answer' => "Most jobs finish within a minute or two. They run in the background, so you can keep working elsewhere — we'll notify you the moment it's ready (look for the bell icon, and optionally enable email alerts in your profile).",
            ],
            [
                'category' => 'features',
                'question' => 'Where is the blog?',
                'answer' => "Our blog lives on a separate site (look for the \"Blog\" link in the navigation) — it's where we publish guides and updates, separate from the in-app blog article generator you use to create your own content. To send the articles you generate to your own WordPress site, use WordPress Sites in your dashboard.",
            ],
            [
                'category' => 'general',
                'question' => 'How do I contact a real human for support?',
                'answer' => 'Ask our AI assistant here in the chat — if it can\'t resolve your issue, it will offer to open a support ticket with our team, transcript included. You can also open a ticket directly from the Support page.',
            ],
            ...self::NEW_FEATURE_FAQS,
        ];

        foreach ($items as $index => $item) {
            FaqItem::updateOrCreate(
                ['question' => $item['question']],
                [...$item, 'sort_order' => $index, 'is_published' => true]
            );
        }
    }
}
