<?php

namespace App\Notifications;

use App\Models\ReferralPayout;
use App\Models\User;
use App\Services\Referrals\PayoutMethodService;
use App\Support\ReferralProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an affiliate when an admin marks their payout request paid — see
 * App\Services\Referrals\ReferralPayoutService::processPayout(). The
 * reference is whatever the admin recorded (a PayPal transaction id, a bank
 * reference) so the affiliate has something to match against their own
 * account.
 */
class ReferralPayoutProcessed extends Notification
{
    use Queueable;

    public function __construct(
        public ReferralPayout $payout,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $amount = ReferralProgram::money($this->payout->amount_cents, $this->payout->currency);
        $portal = $notifiable instanceof User && $notifiable->isAffiliateOnly() ? route('partner.payouts') : route('referrals.index', ['tab' => 'payouts']);

        return (new MailMessage)
            ->subject('Your referral payout has been sent')
            ->line("Your payout of {$amount} has been sent to ".PayoutMethodService::summary($this->payout->payout_method, $this->payout->payout_details).'.')
            ->line("Reference: {$this->payout->reference}")
            ->action('See your payouts', $portal)
            ->line('Thanks for referring people to AffilStack!');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'referral_payout_processed',
            'amount_cents' => $this->payout->amount_cents,
            'currency' => $this->payout->currency,
            'reference' => $this->payout->reference,
            'url' => $notifiable instanceof User && $notifiable->isAffiliateOnly() ? route('partner.payouts') : route('referrals.index'),
        ];
    }
}
