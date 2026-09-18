<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AffiliateApplications\AffiliateApplicationResource;
use App\Filament\Resources\RefundRequests\RefundRequestResource;
use App\Filament\Resources\WalletTransactions\WalletTransactionResource;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Audit item #4: one admin-facing page explaining every settings screen in
 * this panel — what it configures, why it exists, and how to tell it's
 * working. Deliberately built LAST (after the wallet, Connections Health,
 * and refund policy features it documents), rather than up front, so it
 * covers the whole finished system instead of going stale the moment a
 * later audit item added another settings page.
 *
 * Every admin can see this regardless of their department (unlike most
 * pages in this panel — see ScopedToDepartment) — it's reference material,
 * not something that changes anything, and an admin_sub account benefits
 * most from understanding a department they don't have full access to.
 * Content is a plain static array rather than pulling from each settings
 * page directly, since the point is a human-readable explanation of *why*
 * each setting matters, which doesn't live in the settings page's own code.
 */
class AdminDocumentation extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBookOpen;

    protected static string|\UnitEnum|null $navigationGroup = 'System';

    protected static ?string $navigationLabel = 'Documentation';

    protected static ?string $title = 'Admin Documentation';

    protected static ?int $navigationSort = 100;

    protected string $view = 'filament.pages.admin-documentation';

    /**
     * Not scoped to a single department (see class docblock) but still
     * gated to whoever this panel itself allows in — a plain customer
     * account (role "user") has no business here, same rule
     * User::canAccessPanel() already enforces at the HTTP layer.
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->canAccessPanel(Filament::getPanel('admin')) === true;
    }

    /**
     * @return array<int, array{title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'title' => 'Admin login & security',
                'url' => null,
                'body' => [
                    'This panel lives at a custom, hard-to-guess URL instead of the predictable "/admin" — set '.
                        'via the ADMIN_PANEL_PATH environment variable (config/admin.php). Only this admin panel\'s '.
                        'login moved; the regular customer login at /login is untouched and always stays a different '.
                        'URL from this one, on purpose — someone finding one should learn nothing about the other.',
                    'Admin sub-accounts (Filament: System) can be granted access to one or more departments below '.
                        'without being a full admin — see config(\'admin.departments\') for the exact list and what '.
                        'each one covers. A full admin/super-admin always sees everything, including this page.',
                ],
            ],
            [
                'title' => 'Pricing plans & billing',
                'url' => null,
                'body' => [
                    'Plans (Filament: Billing > Plans) are priced to be easy to start on and profitable at scale — '.
                        'see the plans.md pricing audit notes in each plan\'s own history for the cost/margin math '.
                        'behind each tier. A plan\'s price, credits, and limits can be edited any time; existing '.
                        'subscribers keep their current terms until they change plans or renew.',
                    'A customer can upgrade immediately (charged only the prorated difference) or schedule a '.
                        'downgrade for their next renewal — see Billing > Payment Transactions for a record of every '.
                        'proration and plan-change charge. Choosing yearly billing always saves 15% versus paying '.
                        'monthly for a year; that discount is computed once in Plan::yearlyPriceCentsFor() so it can '.
                        'never drift between the pricing page and what a customer is actually charged.',
                    'A customer\'s saved payment methods (Billing > Payment Transactions shows the charges; the '.
                        'methods themselves are on their own dashboard billing page) are captured automatically the '.
                        'moment a charge succeeds — there is no "add a card" admin action, and nothing here needs to '.
                        'be configured for this to work.',
                ],
            ],
            [
                'title' => 'Payment gateways',
                'url' => PaymentGatewaySettings::getUrl(),
                'body' => [
                    'Flutterwave, Stripe, and PayPal are offered to everyone outside Nigeria; Paystack is offered '.
                        'only to a checkout resolved as Nigerian (with a manual "switch to Naira/USD" escape hatch '.
                        'on the signup and billing pages for when that guess is wrong). Each gateway has its own '.
                        'on/off toggle here — turning one off removes it from checkout immediately, on top of the '.
                        'country rule, never instead of it.',
                    'Enter each gateway\'s secret key (and, for Stripe/Flutterwave, its webhook secret/hash) on this '.
                        'page. Use Connections Health below to confirm a key was accepted before relying on it in '.
                        'production — a typo here fails silently at checkout otherwise.',
                    'The automatic 48-hour/zero-usage refund policy (see below) issues real refunds through '.
                        'whichever gateway a customer originally paid with, so a gateway\'s credentials must stay '.
                        'valid for as long as any of its customers could still be inside that window.',
                ],
            ],
            [
                'title' => 'PartnerStack (affiliate tracking)',
                'url' => PartnerStackSettings::getUrl(),
                'body' => [
                    'Connects AffilStack\'s own referral program to PartnerStack for cross-platform affiliate '.
                        'tracking. Enter the API key/public key pair from your PartnerStack dashboard here, then use '.
                        'Connections Health to verify the key was accepted.',
                    'This is separate from the in-app Referral Payouts/Payout Wallet system below — PartnerStack is '.
                        'an external tracking integration, while Referral Payouts and the Payout Wallet are how '.
                        'commissions actually get calculated and paid out from inside AffilStack itself.',
                ],
            ],
            [
                'title' => 'Payout wallet (affiliate payouts)',
                'url' => WalletTransactionResource::getUrl(),
                'body' => [
                    'A running ledger you fund with real money you\'ve already moved into your own Flutterwave '.
                        'balance or PayPal account — "Add funds" here records that top-up; it never moves money by '.
                        'itself. The balance shown is always the sum of every ledger row for that currency, never a '.
                        'separately-stored number, so it can never drift out of sync with what actually happened.',
                    'From Referral Payouts, a "Disburse from wallet" button appears next to any requested payout '.
                        'this wallet can pay automatically — Flutterwave Transfers for a Nigerian bank_transfer '.
                        'payout (needs a Flutterwave bank code on the affiliate\'s payout details), PayPal Payouts '.
                        'for everyone else. It only appears when the wallet actually has enough balance in that '.
                        'currency; otherwise, "Mark paid" still works exactly as before once you\'ve sent the money '.
                        'yourself.',
                    'Every disbursement requires an admin to click the button — nothing here ever moves real money '.
                        'on a schedule or without a human deciding to do it right then.',
                ],
            ],
            [
                'title' => 'Connections Health (AI-assisted verification)',
                'url' => ConnectionsHealth::getUrl(),
                'body' => [
                    'One page that checks every external integration this panel configures — payment gateways, '.
                        'PartnerStack, Google/LinkedIn/TikTok/Instagram, the Brain agent\'s Anthropic key and Meta '.
                        'MCP connection, and HeyGen — and reports one of four states per item: not configured, '.
                        'configured but never verified, verified and working, or verified and failing.',
                    '"Run all live checks" actually calls each service\'s API with the stored credentials — the '.
                        'same verification each settings page already runs when you save it, just gathered in one '.
                        'place so you don\'t have to open eight pages to confirm nothing has quietly broken.',
                    'A few integrations (LinkedIn, TikTok, Instagram) use an OAuth consent flow that can\'t be '.
                        'pinged with a stored key alone — those are marked "Configured" with a note on exactly what '.
                        'to click-test on the real frontend instead of a live API check.',
                    'Every item has an "Explain with AI" button that turns its current status into a plain-language '.
                        'explanation of what\'s wrong and how to fix it, for whoever is on call and didn\'t set the '.
                        'integration up originally.',
                ],
            ],
            [
                'title' => 'Funding Watch (Vault, the funding-monitor agent)',
                'url' => FundingHealth::getUrl(),
                'body' => [
                    'Vault watches every account that needs to stay funded so a user\'s action never fails '.
                        'mid-flight — HeyGen\'s video-render credits and the payout wallet balance are checked live '.
                        'against a threshold you set here; Anthropic API billing and Meta ad spend have no balance '.
                        'an API key alone can check, so those instead get an admin-set reminder cadence (in days) '.
                        'that Vault nags you about until you click "I topped up — reset reminder".',
                    'Every check runs daily on its own (see System > Scheduled Task Runs for the run history), and '.
                        '"Run checks now" re-checks everything immediately. A low balance or an overdue reminder '.
                        'notifies every full admin exactly once per crossing — it won\'t repeat the same alert every '.
                        'day while nothing has changed, and it notifies again automatically once the balance drops '.
                        'below the threshold a second time (or, for a manual reminder, once it falls due again after '.
                        'you reset it).',
                ],
            ],
            [
                'title' => 'Branding: logo, colors & homepage',
                'url' => BrandSettings::getUrl(),
                'body' => [
                    'Two separate logo uploads, not one: the rectangular/wide logo is used in the site header, the '.
                        'sign-in/sign-up pages, and the dashboard sidebar; the square/circular one is used as the '.
                        'browser tab icon (favicon) and the mobile home-screen icon, and anywhere else a square mark '.
                        'fits better than a wide one. Uploading either one updates every one of those places '.
                        'immediately — there\'s no separate favicon generator step and nothing else to configure.',
                    'The homepage hero can show an image or an embedded YouTube video (paste the video URL here) — '.
                        'leave it on "None" to keep the plain text hero. The "Simple, transparent pricing" section '.
                        'on the homepage always mirrors whatever is live on Billing > Plans (the same three plans '.
                        'shown on the real pricing page), so there\'s nothing extra to keep in sync there either.',
                ],
            ],
            [
                'title' => 'Affiliate program landing page',
                'url' => AffiliateApplicationResource::getUrl(),
                'body' => [
                    'Every paying customer is already an affiliate automatically the moment they log in (Dashboard '.
                        '> Referrals) — this page is only for someone who wants to become an affiliate WITHOUT ever '.
                        'becoming a customer. They apply from the public affiliate landing page (served at the '.
                        'AFFILIATE_SUBDOMAIN environment variable\'s subdomain when set, e.g. '.
                        'affiliate.affilstack.com — otherwise it falls back to a plain /affiliate page on the main '.
                        'domain so the feature works before real DNS is configured), and every application shows up '.
                        'here as "pending" until you Approve or Reject it.',
                    'Approving an application creates their account and emails them a one-time link to set their '.
                        'own password — there\'s no admin approval queue after that; they land straight in the '.
                        'Referrals dashboard once they set it. This kind of account can only ever reach the '.
                        'Referrals page, their own profile, and logout — never any paid feature, since they were '.
                        'never a customer. Rejecting an application emails the reason you enter and creates no '.
                        'account at all.',
                ],
            ],
            [
                'title' => 'Refund & cancellation policy',
                'url' => RefundRequestResource::getUrl(),
                'body' => [
                    'Refunds are fully automatic, governed by one strict rule: a payment made within the last 48 '.
                        'hours, on an account that has not yet spent a single AI credit, is refunded instantly when '.
                        'the customer clicks "Request refund" on their own billing page — there is no admin approval '.
                        'queue. This is deliberate: a discretionary "reasonable case" policy can always be argued '.
                        'with, while an objective, automatic rule can\'t be talked into bending, which is what '.
                        'actually protects the business financially.',
                    'A refund cancels the subscription immediately, claws back exactly the AI credits that '.
                        'subscription granted (safe to do in full, since eligibility already proved none were '.
                        'spent), and reverses any referral commission tied to that payment — see Refund Requests '.
                        '(this page\'s link) for a read-only audit trail of every refund issued this way, including '.
                        'ones the gateway rejected.',
                    'The public Refund & Cancellation Policy page (Content > Site Pages, slug "refund-policy") '.
                        'explains this in customer-facing language, and every signup — password or Google — '.
                        'requires accepting it before an account is created.',
                ],
            ],
            [
                'title' => 'Caching & performance',
                'url' => null,
                'body' => [
                    'Public marketing content (the pricing page, homepage feature cards, FAQ, and static pages '.
                        'like About/Terms/Privacy/Refund Policy) is cached indefinitely and refreshed automatically '.
                        'the instant you save a change in Filament — there is no "clear cache" step to remember '.
                        'after editing Plans, Site Pages, FAQ, or Homepage Features.',
                    'Those same pages also tell browsers (and any CDN in front of the site) they can be reused for '.
                        'a few minutes, so a repeat visit doesn\'t always round-trip through the server — this backs '.
                        'off automatically on any response carrying a flash message, so an error or success banner '.
                        'is never accidentally shown to the wrong visitor.',
                    'If page content ever looks stale after an edit, it\'s almost certainly a browser-level cache '.
                        '(hard-refresh fixes it) rather than the server — server-side content caching is '.
                        'invalidated the moment you hit Save.',
                ],
            ],
            [
                'title' => 'Other integrations',
                'url' => null,
                'body' => [
                    'Google Login, LinkedIn Connect, TikTok Publishing, Instagram Publishing, Brain Settings (the '.
                        'AI agent\'s Anthropic key and Meta MCP connection), and UGC Video Settings (HeyGen) each '.
                        'have their own settings page under System/AI Agents in the navigation. Every one of them '.
                        'shows up on Connections Health above — that\'s the fastest way to confirm all of them are '.
                        'actually working rather than opening each page individually.',
                ],
            ],
        ];
    }
}
