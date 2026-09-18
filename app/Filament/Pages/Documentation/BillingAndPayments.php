<?php

namespace App\Filament\Pages\Documentation;

use App\Filament\Clusters\Documentation;
use App\Filament\Pages\Documentation\Concerns\VisibleToAnyAdmin;
use App\Filament\Pages\PaymentGatewaySettings;
use App\Filament\Resources\CreditPackages\CreditPackageResource;
use App\Filament\Resources\RefundRequests\RefundRequestResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class BillingAndPayments extends Page
{
    use VisibleToAnyAdmin;

    protected static ?string $cluster = Documentation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $navigationLabel = 'Billing & Payments';

    protected static ?string $title = 'Billing & Payments';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.documentation.section-page';

    public function headerIcon(): Heroicon
    {
        return Heroicon::OutlinedCreditCard;
    }

    public function intro(): string
    {
        return 'How plans, gateways, one-time credit purchases, and refunds all fit together.';
    }

    /**
     * @return array<int, array{icon: Heroicon, title: string, url: string|null, body: array<int, string>}>
     */
    public function sections(): array
    {
        return [
            [
                'icon' => Heroicon::OutlinedTag,
                'title' => 'Pricing plans & billing',
                'url' => null,
                'body' => [
                    'Plans (Filament: Billing > Plans) are priced to be easy to start on and profitable at scale. '.
                        'A plan\'s price, credits, and limits can be edited any time; existing subscribers keep '.
                        'their current terms until they change plans or renew.',
                    'A customer can upgrade immediately (charged only the prorated difference) or schedule a '.
                        'downgrade for their next renewal — see Billing > Payment Transactions for a record of '.
                        'every proration and plan-change charge. Choosing yearly billing always saves 15% versus '.
                        'paying monthly for a year; that discount is computed once in Plan::yearlyPriceCentsFor() '.
                        'so it can never drift between the pricing page and what a customer is actually charged.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedBanknotes,
                'title' => 'Payment gateways',
                'url' => PaymentGatewaySettings::getUrl(),
                'body' => [
                    'Flutterwave, Stripe, and PayPal are offered to everyone outside Nigeria; Paystack is offered '.
                        'only to a checkout resolved as Nigerian (with a manual "switch to Naira/USD" escape hatch '.
                        'on the signup and billing pages). Each gateway has its own on/off toggle here — turning '.
                        'one off removes it from checkout immediately, on top of the country rule, never instead '.
                        'of it.',
                    'Enter each gateway\'s secret key (and, for Stripe/Flutterwave, its webhook secret/hash) on '.
                        'this page. Use Connections Health (Integrations & Health) to confirm a key was accepted '.
                        'before relying on it in production — a typo here fails silently at checkout otherwise.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedBolt,
                'title' => 'Credit top-ups',
                'url' => CreditPackageResource::getUrl(),
                'body' => [
                    '"Users should be able to buy more credit tokens if their monthly allocation finishes" — a '.
                        'customer who runs out mid-cycle buys a one-time package from their dashboard (Buy Credits) '.
                        'instead of waiting for renewal or upgrading. Credits post instantly on payment and never '.
                        'expire. Every package\'s name, credit amount, price, and "featured" badge can be edited '.
                        'here any time.',
                    'Priced on a volume-discount curve — comfortably above what a credit costs to grant, and '.
                        'never cheaper than what the same credits would cost bundled into your highest plan tier, '.
                        'so buying a top-up is always convenient but never a way to undercut upgrading. Checkout '.
                        'reuses the exact same gateway selection as a plan subscription above.',
                ],
            ],
            [
                'icon' => Heroicon::OutlinedReceiptRefund,
                'title' => 'Refund & cancellation policy',
                'url' => RefundRequestResource::getUrl(),
                'body' => [
                    'Refunds are fully automatic, governed by one strict rule: a payment made within the last 48 '.
                        'hours, on an account that has not yet spent a single AI credit, is refunded instantly when '.
                        'the customer clicks "Request refund" on their own billing page — there is no admin '.
                        'approval queue. An objective, automatic rule can\'t be argued with the way a discretionary '.
                        '"reasonable case" policy can, which is what actually protects the business financially.',
                    'A refund cancels the subscription immediately, claws back exactly the AI credits that '.
                        'subscription granted, and reverses any referral commission tied to that payment — see '.
                        'Refund Requests (this page\'s link) for a read-only audit trail of every refund issued '.
                        'this way, including ones the gateway rejected.',
                ],
            ],
        ];
    }
}
