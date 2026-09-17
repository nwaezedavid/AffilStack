<?php

namespace App\Notifications;

use App\Models\ReferralPayout;
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
        $amount = number_format($this->payout->amount_cents / 100, 2);

        return (new MailMessage)
            ->subject('Your referral payout has been sent')
            ->line("Your payout of \${$amount} {$this->payout->currency} has been sent to your {$this->payout->payout_method}.")
            ->line("Reference: {$this->payout->reference}")
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
            'url' => route('referrals.index'),
        ];
    }
}
