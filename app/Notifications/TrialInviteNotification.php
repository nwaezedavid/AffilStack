<?php

namespace App\Notifications;

use App\Models\CustomerWallet;
use App\Models\TrialInvite;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * The admin-only free trial invite. The signed link in it is the only way
 * to start a trial — see TrialInviteService.
 */
class TrialInviteNotification extends Notification
{
    use Queueable;

    public function __construct(public TrialInvite $invite, public string $url) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $plan = $this->invite->plan;
        $price = CustomerWallet::format($this->invite->priceCents(), (string) ($plan?->currency ?: 'USD'));
        $cycle = $this->invite->billing_cycle === 'yearly' ? 'year' : 'month';

        return (new MailMessage)
            ->subject("You're invited to try AffilStack free for 7 days")
            ->greeting("Hi {$this->invite->name},")
            ->line("You've been invited to a 7-day free trial of the AffilStack {$plan?->name} plan.")
            ->line("Save a card to start. You won't be charged for the plan during the trial, and you can cancel any time before it ends. After 7 days the plan is {$price} per {$cycle}.")
            ->action('Start my free trial', $this->url)
            ->line('This link works once and expires on '.$this->invite->expires_at->format('M j, Y').'.');
    }
}
