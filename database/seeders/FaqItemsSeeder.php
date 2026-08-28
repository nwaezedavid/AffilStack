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
    public function run(): void
    {
        $items = [
            [
                'category' => 'account',
                'question' => 'How do I create an AffiliStack account?',
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
                'answer' => "We offer a money-back guarantee within the first 7 days of a new subscription if you're not satisfied. Open a support ticket from your dashboard to request one.",
            ],
            [
                'category' => 'features',
                'question' => 'What are credits and how do they work?',
                'answer' => 'Credits are what AffiliStack uses to meter AI usage — offer research, blog articles, and LinkedIn content each consume a set number of credits per generation. Your plan includes a monthly credit allowance, visible in your dashboard sidebar.',
            ],
            [
                'category' => 'features',
                'question' => 'How long does offer research or content generation take?',
                'answer' => "Most jobs finish within a minute or two. They run in the background, so you can keep working elsewhere — we'll notify you the moment it's ready (look for the bell icon, and optionally enable email alerts in your profile).",
            ],
            [
                'category' => 'features',
                'question' => 'Where is the blog?',
                'answer' => "Our blog lives on a separate site (look for the \"Blog\" link in the navigation) — it's where we publish guides and updates, separate from the in-app blog article generator you use to create your own content.",
            ],
            [
                'category' => 'general',
                'question' => 'How do I contact a real human for support?',
                'answer' => 'Ask our AI assistant here in the chat — if it can\'t resolve your issue, it will offer to open a support ticket with our team, transcript included. You can also open a ticket directly from the Support page.',
            ],
        ];

        foreach ($items as $index => $item) {
            FaqItem::updateOrCreate(
                ['question' => $item['question']],
                [...$item, 'sort_order' => $index, 'is_published' => true]
            );
        }
    }
}
