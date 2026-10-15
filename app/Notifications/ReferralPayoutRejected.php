<?php

namespace App\Notifications;

use App\Models\ReferralPayout;
use App\Models\User;
use App\Support\ReferralProgram;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to an affiliate when an admin can't fulfill their payout request
 * (bad payout details, a commission that turned out to be fraudulent,
 * etc.) — see App\Services\Referrals\ReferralPayoutService::rejectPayout().
 * The underlying commission events are released back to "approved" and
 * unattached, so the affiliate can fix the issue and request again.
 */
class ReferralPayoutRejected extends Notification
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
            ->subject('Your referral payout request needs attention')
            ->line("We weren't able to process your payout request of {$amount}.")
            ->line($this->payout->note ?? 'Please check your payout details and request again.')
            ->line('Your commissions are still approved and available — update your payout details and submit a new request whenever you\'re ready.')
            ->action('Review your payout details', $portal);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'referral_payout_rejected',
            'amount_cents' => $this->payout->amount_cents,
            'currency' => $this->payout->currency,
            'note' => $this->payout->note,
            'url' => $notifiable instanceof User && $notifiable->isAffiliateOnly() ? route('partner.payouts') : route('referrals.index'),
        ];
    }
}
