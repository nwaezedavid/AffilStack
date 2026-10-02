<?php

namespace App\Services\ApiDocs;

/**
 * The outbound webhook events a user can subscribe to (config('webhooks.events'))
 * with a plain-language "fires when" and an example of the exact `data`
 * each one carries — kept beside the code that builds those payloads
 * (CrmContact, Generation, Referral, Earning and ApiWalletManager's
 * booted()/dispatch calls), and used both for the API hub's event
 * catalogue and for "Send test event".
 */
class WebhookEventCatalog
{
    /**
     * @return array<string, array{label: string, fires_when: string, data: array<string, mixed>}>
     */
    public function events(): array
    {
        return collect(config('webhooks.events'))
            ->map(fn (string $label, string $event) => [
                'label' => $label,
                'fires_when' => $this->firesWhen($event),
                'data' => $this->sampleData($event),
            ])
            ->all();
    }

    /**
     * The full JSON body AffilStack POSTs for $event.
     *
     * @return array<string, mixed>
     */
    public function sampleBody(string $event): array
    {
        return [
            'event' => $event,
            'data' => $this->sampleData($event),
            'delivery_id' => 1287,
            'timestamp' => '2026-10-01T09:30:00+00:00',
        ];
    }

    protected function firesWhen(string $event): string
    {
        return match ($event) {
            'generation.completed' => 'Any AI generation finishes — offer research, a blog article, a LinkedIn post, a YouTube script, a UGC video and so on. Use "module" to react to just one kind.',
            'crm_contact.created' => 'A contact is added to your CRM from anywhere: the dashboard, Local Leads, the Chrome extension or the API.',
            'referral.converted' => 'Someone you referred becomes a paying customer.',
            'earning.recorded' => 'An affiliate earning is added, by hand or by CSV import.',
            'api_wallet.low_balance' => 'Your API wallet drops to or below the low-balance threshold (fires once per crossing).',
            'api_wallet.topped_up' => 'Money is added to your API wallet, by checkout or auto-recharge.',
            default => 'See the event name.',
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function sampleData(string $event): array
    {
        return match ($event) {
            'generation.completed' => ['generation_id' => 981, 'offer_id' => 42, 'module' => 'blog_article', 'completed_at' => '2026-10-01T09:30:00+00:00'],
            'crm_contact.created' => ['contact_id' => 77, 'name' => 'Jane Doe', 'email' => 'jane@acme.example', 'company' => 'Acme Inc.', 'source' => 'api', 'status' => 'new'],
            'referral.converted' => ['referral_id' => 15, 'referred_user_id' => 302, 'converted_at' => '2026-10-01T09:30:00+00:00'],
            'earning.recorded' => ['earning_id' => 64, 'amount_cents' => 4900, 'currency' => 'USD', 'network' => 'ShareASale', 'status' => 'pending'],
            'api_wallet.low_balance' => ['balance_cents' => 450, 'threshold_cents' => (int) config('api_billing.low_balance_threshold_cents', 500)],
            'api_wallet.topped_up' => ['type' => 'topup', 'amount_cents' => 2500, 'balance_after_cents' => 2950],
            default => [],
        };
    }
}
