<?php

namespace App\Console\Commands\Subscriptions;

use App\Services\Payments\SubscriptionRenewalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('subscriptions:send-renewal-reminders')]
#[Description('Email Flutterwave subscribers a few days before their period ends, since Flutterwave never auto-renews')]
class SendRenewalReminders extends Command
{
    public function handle(SubscriptionRenewalService $renewals): void
    {
        $count = $renewals->sendRenewalReminders();

        $this->info("Sent {$count} renewal reminder(s).");
    }
}
