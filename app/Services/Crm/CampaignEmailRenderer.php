<?php

namespace App\Services\Crm;

use App\Models\CrmContact;
use App\Models\EmailConnection;
use App\Models\User;

/**
 * Renders the HTML for a campaign email or inbox reply (emails.crm.campaign)
 * and works out the sender name and compliance footer for it.
 */
class CampaignEmailRenderer
{
    /**
     * @param  array{quoted?: string|null, tracking_token?: string|null, unsubscribe?: bool}  $options
     */
    public function render(User $user, CrmContact $contact, string $body, array $options = []): string
    {
        $connection = $user->emailConnection;
        $withUnsubscribe = $options['unsubscribe'] ?? true;

        return view('emails.crm.campaign', [
            'bodyHtml' => $this->toHtml($body),
            'senderName' => $this->senderName($user, $connection),
            'quotedHtml' => ! empty($options['quoted']) ? nl2br(e($options['quoted'])) : null,
            'businessName' => $this->businessName($user, $connection),
            'businessAddress' => $this->businessAddress($user, $connection),
            'unsubscribeUrl' => $withUnsubscribe ? route('crm.unsubscribe', $contact->unsubscribeToken()) : null,
            'openPixelUrl' => ! empty($options['tracking_token']) ? route('crm.track.open', $options['tracking_token']) : null,
        ])->render();
    }

    /**
     * Escapes plain text, keeps line breaks and turns bare URLs into links.
     */
    public function toHtml(string $body): string
    {
        $html = nl2br(e($body));

        return preg_replace_callback('#\bhttps?://[^\s<>"\']+#i', function (array $m): string {
            $url = rtrim($m[0], '.,;:!?)');
            $trail = substr($m[0], strlen($url));

            return '<a href="'.$url.'" style="color:#2563eb;">'.$url.'</a>'.$trail;
        }, $html) ?? $html;
    }

    public function senderName(User $user, ?EmailConnection $connection): string
    {
        return (string) ($connection?->credential('from_name') ?: $user->name);
    }

    public function businessName(User $user, ?EmailConnection $connection): ?string
    {
        return $connection?->footer_business_name ?: ($user->company_name ?: $user->name);
    }

    public function businessAddress(User $user, ?EmailConnection $connection): ?string
    {
        if ($connection?->footer_address) {
            return $connection->footer_address;
        }

        $parts = array_filter([$user->city, $user->state_region, $user->country]);

        return $parts ? implode(', ', $parts) : null;
    }
}
