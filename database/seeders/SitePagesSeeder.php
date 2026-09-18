<?php

namespace Database\Seeders;

use App\Models\SitePage;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Starter content for the platform's static pages (About, Terms, Privacy,
 * Refund Policy, Cookie Policy). Admin can edit every word from Filament
 * (Content > Site Pages) without a code deploy.
 *
 * This is professionally-toned starter copy, not legal advice — it should
 * be reviewed by a lawyer familiar with the operator's business and
 * jurisdiction before the site goes live, especially the Terms, Privacy
 * Policy, and Refund Policy.
 */
class SitePagesSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        foreach ($this->pages() as $page) {
            SitePage::updateOrCreate(['slug' => $page['slug']], $page);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function pages(): array
    {
        return [
            [
                'slug' => 'about',
                'title' => 'About us',
                'meta_description' => 'Learn what AffilStack is, who it is built for, and how it helps affiliate marketers research offers, create content, and get paid faster.',
                'is_published' => true,
                'content' => <<<'HTML'
                    <p>AffilStack exists to remove the busywork from affiliate marketing so you can spend your time on the two things that actually move the needle: finding good offers and putting them in front of the right people.</p>

                    <h2>What we do</h2>
                    <p>Before AffilStack, promoting an offer well meant switching between a research tool, a writing tool, a design tool, a link tracker, a CRM, and a spreadsheet just to keep it all straight. AffilStack puts all of that in one place: give it a product, a link, or a niche, and it researches your ideal buyer, writes the content, tracks every click and sale, and keeps your leads organized — all from one dashboard.</p>

                    <h2>Who it's for</h2>
                    <p>AffilStack is built for two kinds of people, and both get the full platform:</p>
                    <ul>
                        <li><strong>Individual marketers</strong> who want to run a serious affiliate business without hiring a team or juggling a dozen subscriptions.</li>
                        <li><strong>Growing teams</strong> who need to collaborate on campaigns, share what's working, and keep everyone's output organized in one account.</li>
                    </ul>

                    <h2>How we work</h2>
                    <p>We build in the open with our users: every feature on this platform exists because affiliate marketers asked for it, and we keep shipping improvements based on what actually helps people close more sales. If there's something that would make your work easier, <a href="/contact">tell us</a> — we read every message.</p>
                    HTML,
            ],
            [
                'slug' => 'terms',
                'title' => 'Terms and Conditions',
                'meta_description' => 'The terms that govern your use of AffilStack, including subscriptions, billing, acceptable use, and affiliate program rules.',
                'is_published' => true,
                'content' => <<<'HTML'
                    <p>These Terms and Conditions ("Terms") govern your access to and use of AffilStack (the "Platform," "we," "us," or "our"). By creating an account or using the Platform, you agree to these Terms. If you do not agree, do not use the Platform.</p>

                    <h2>1. Accounts and eligibility</h2>
                    <p>Accounts on AffilStack are created only through the purchase of a paid subscription plan — there is no free, open registration. You must provide accurate information when purchasing a plan and setting your password, and you are responsible for all activity that occurs under your account. You must be at least 18 years old, or the age of majority in your jurisdiction, to use the Platform.</p>

                    <h2>2. Subscriptions, billing, and renewal</h2>
                    <ul>
                        <li>Plans are billed on a recurring basis (monthly or annually, as selected) until cancelled.</li>
                        <li>Your subscription renews automatically at the end of each billing period using the payment method on file, unless you cancel before the renewal date.</li>
                        <li>If a renewal payment fails, your access to paid features may be suspended until payment is resolved. Continued failure to pay may result in account downgrade or termination.</li>
                        <li>Prices, plan features, and billing terms may change with reasonable advance notice; continued use after a change takes effect constitutes acceptance of the new terms.</li>
                    </ul>

                    <h2>3. Refunds and cancellation</h2>
                    <p>Refunds are governed by our <a href="/refund-policy">Refund &amp; Cancellation Policy</a>, which forms part of these Terms.</p>

                    <h2>4. Acceptable use</h2>
                    <p>You agree not to use the Platform to:</p>
                    <ul>
                        <li>Promote illegal products or services, or content that infringes another party's intellectual property.</li>
                        <li>Send unsolicited bulk email ("spam") or otherwise violate applicable anti-spam or electronic communications laws.</li>
                        <li>Misrepresent AI-generated or UGC content as showing a real person's genuine, unscripted testimony where doing so would be deceptive or unlawful.</li>
                        <li>Attempt to circumvent plan limits, resell access to the Platform without authorization, or interfere with the Platform's normal operation.</li>
                        <li>Violate the affiliate disclosure requirements of the FTC or any equivalent regulator in your market when promoting offers using content generated on the Platform.</li>
                    </ul>
                    <p>We may suspend or terminate accounts that violate this section, with or without notice, depending on severity.</p>

                    <h2>5. Content you create</h2>
                    <p>Subject to your compliance with these Terms and continued good standing on a paid plan, you own the rights to the marketing content, images, and videos you generate through the Platform for your own commercial use. AffilStack retains no ownership claim over content you generate, but we may use anonymized, aggregate data about Platform usage to improve our services.</p>

                    <h2>6. AI-generated content</h2>
                    <p>Content generated through the Platform — including written copy, images, voiceovers, and UGC-style videos — is produced with the assistance of third-party and proprietary AI systems. You are responsible for reviewing generated content before publishing it, for its accuracy and legality, and for complying with any disclosure obligations (for example, marking AI-generated or sponsored content) that apply in the markets where you publish it.</p>

                    <h2>7. Affiliate program</h2>
                    <p>If you participate in the AffilStack Affiliate Program, your participation is additionally governed by the affiliate program's own terms, published on the affiliate program site, which cover commission structure, attribution, payouts, and prohibited promotional practices (including a strict prohibition on bidding on our brand terms).</p>

                    <h2>8. Service availability and changes</h2>
                    <p>We aim to keep the Platform available at all times but do not guarantee uninterrupted access. We may perform scheduled maintenance, and we will make reasonable efforts to notify you in advance of any maintenance window that may affect your ability to use the Platform.</p>

                    <h2>9. Disclaimer of warranties</h2>
                    <p>The Platform is provided "as is" and "as available," without warranties of any kind, whether express or implied, including implied warranties of merchantability, fitness for a particular purpose, and non-infringement. We do not guarantee any specific marketing result, sale, or income from your use of the Platform.</p>

                    <h2>10. Limitation of liability</h2>
                    <p>To the maximum extent permitted by law, AffilStack and its owners, employees, and contractors will not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenue, arising from your use of the Platform.</p>

                    <h2>11. Termination</h2>
                    <p>You may cancel your subscription at any time; access continues until the end of the current billing period. We may suspend or terminate your account for breach of these Terms, non-payment, or conduct that harms the Platform or other users.</p>

                    <h2>12. Changes to these Terms</h2>
                    <p>We may update these Terms from time to time. Material changes will be communicated by email or an in-app notice. Continued use of the Platform after a change takes effect constitutes acceptance of the revised Terms.</p>

                    <h2>13. Contact</h2>
                    <p>Questions about these Terms can be sent through our <a href="/contact">Contact page</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'privacy',
                'title' => 'Privacy Policy',
                'meta_description' => 'How AffilStack collects, uses, and protects your personal data, including payment, usage, and account information.',
                'is_published' => true,
                'content' => <<<'HTML'
                    <p>This Privacy Policy explains what personal data AffilStack collects, why we collect it, and the choices you have about it.</p>

                    <h2>1. Information we collect</h2>
                    <ul>
                        <li><strong>Account information:</strong> name, email address, and password (stored encrypted) when you complete a purchase and set up your account.</li>
                        <li><strong>Payment information:</strong> billing details are collected and processed by our payment processors (Stripe and/or Flutterwave) — we do not store your full card number on our servers.</li>
                        <li><strong>Usage data:</strong> the content you generate, links you create, contacts you save, and how you interact with the Platform, so we can operate and improve the service.</li>
                        <li><strong>Communications:</strong> messages you send us through the Contact form, support tickets, or email.</li>
                        <li><strong>Technical data:</strong> IP address, browser type, and device information, collected automatically for security and analytics purposes.</li>
                    </ul>

                    <h2>2. How we use your information</h2>
                    <ul>
                        <li>To create and maintain your account and process subscription payments and renewals.</li>
                        <li>To provide the Platform's features, including AI content generation, link tracking, and CRM tools.</li>
                        <li>To communicate with you about your account, billing, support requests, and — where you have not opted out — product updates.</li>
                        <li>To detect, investigate, and prevent fraud, abuse, and security incidents.</li>
                        <li>To improve the Platform based on aggregated, anonymized usage patterns.</li>
                    </ul>

                    <h2>3. How we share your information</h2>
                    <p>We do not sell your personal data. We share data only with:</p>
                    <ul>
                        <li>Payment processors (Stripe, Flutterwave) to process your subscription.</li>
                        <li>AI service providers (such as Anthropic, OpenAI, and Google) as needed to generate the content you request, limited to what each request requires.</li>
                        <li>Infrastructure and email-delivery providers that host the Platform and deliver transactional email on our behalf.</li>
                        <li>Law enforcement or regulators where required by law.</li>
                    </ul>

                    <h2>4. Cookies</h2>
                    <p>We use cookies to keep you signed in, remember preferences, and understand how the Platform is used. See our <a href="/cookie-policy">Cookie Policy</a> for details and how to control them.</p>

                    <h2>5. Data retention</h2>
                    <p>We keep your account data for as long as your account is active. Generated video files created by the UGC feature are automatically deleted after 72 hours regardless of account status, to manage server load — download anything you want to keep before then. You may request deletion of your account and associated personal data by contacting us; some records (such as billing history) may be retained longer where required for legal or accounting purposes.</p>

                    <h2>6. Your rights</h2>
                    <p>Depending on where you live, you may have rights to access, correct, export, or delete your personal data, and to object to or restrict certain processing. To exercise any of these rights, contact us through the <a href="/contact">Contact page</a>.</p>

                    <h2>7. Security</h2>
                    <p>We use industry-standard safeguards, including encryption in transit and at rest for sensitive data, to protect your information. No system is completely secure, and we cannot guarantee absolute security.</p>

                    <h2>8. Children's privacy</h2>
                    <p>The Platform is not directed to individuals under 18, and we do not knowingly collect personal data from children.</p>

                    <h2>9. Changes to this policy</h2>
                    <p>We may update this Privacy Policy from time to time. Material changes will be communicated by email or an in-app notice.</p>

                    <h2>10. Contact</h2>
                    <p>Questions about this Privacy Policy can be sent through our <a href="/contact">Contact page</a>.</p>
                    HTML,
            ],
            [
                'slug' => 'refund-policy',
                'title' => 'Refund & Cancellation Policy',
                'meta_description' => 'AffilStack\'s automatic 48-hour, no-usage refund policy, and how to cancel your subscription.',
                'is_published' => true,
                'content' => <<<'HTML'
                    <h2>48-hour, no-usage refund policy</h2>
                    <p>You're eligible for a full, automatic refund of your most recent payment if both of the following are true: it was made within the last 48 hours, and you have not yet used any AI credits on your account. There's no approval queue — if both conditions are met, requesting a refund from your dashboard's billing page refunds your payment and cancels your subscription immediately.</p>

                    <h2>How to request a refund</h2>
                    <p>Open your dashboard's billing page. If you're currently eligible, a "Request refund" button is shown there along with how much time is left in your 48-hour window — click it to refund the payment instantly to your original payment method. Once you've used even one credit, or the 48-hour window has passed, the payment is no longer refundable this way.</p>

                    <h2>Why usage ends eligibility</h2>
                    <p>AI credits represent real, immediate cost to us the moment they're used, so a refund is only offered while an account has cost us nothing yet. This keeps the policy simple, instant, and fair to use without needing a case-by-case review.</p>

                    <h2>Cancelling your subscription</h2>
                    <p>You can cancel your subscription at any time from your dashboard's billing settings. Cancelling stops future renewals — you keep access to your paid plan until the end of the billing period you've already paid for, after which your account reverts to a locked, non-renewing state until you resubscribe. Cancelling on its own does not refund the current period unless you're still within the 48-hour, no-usage window above.</p>

                    <h2>Renewal charges</h2>
                    <p>Subscriptions renew automatically. A renewal charge is eligible for the same automatic refund as any other payment, under the same 48-hour, no-usage rule.</p>

                    <h2>Affiliate program payouts</h2>
                    <p>This policy covers subscription payments only. Affiliate commission payouts are governed separately by the AffilStack Affiliate Program terms.</p>
                    HTML,
            ],
            [
                'slug' => 'cookie-policy',
                'title' => 'Cookie Policy',
                'meta_description' => 'What cookies AffilStack uses, why, and how to control them.',
                'is_published' => true,
                'content' => <<<'HTML'
                    <p>This Cookie Policy explains what cookies AffilStack uses and why.</p>

                    <h2>What are cookies</h2>
                    <p>Cookies are small text files stored on your device that help a website remember information about your visit.</p>

                    <h2>Cookies we use</h2>
                    <ul>
                        <li><strong>Essential cookies:</strong> required to keep you signed in and to remember basic security tokens (such as CSRF protection). The Platform cannot function without these.</li>
                        <li><strong>Analytics cookies:</strong> help us understand how visitors use the Platform (for example, via Google Analytics, where enabled) so we can improve it. These are only set if analytics is enabled in our site settings.</li>
                        <li><strong>Advertising cookies:</strong> may be used on our marketing pages to measure the effectiveness of ad campaigns that brought you to the site.</li>
                    </ul>

                    <h2>Controlling cookies</h2>
                    <p>Most browsers let you block or delete cookies through their settings. Blocking essential cookies will prevent you from signing in or using the dashboard. Blocking analytics or advertising cookies will not affect your ability to use the Platform.</p>

                    <h2>Changes to this policy</h2>
                    <p>We may update this Cookie Policy as our use of cookies changes. Check back periodically for updates.</p>

                    <h2>Contact</h2>
                    <p>Questions about this Cookie Policy can be sent through our <a href="/contact">Contact page</a>.</p>
                    HTML,
            ],
        ];
    }
}
