<?php

namespace App\Console\Commands\Subscriptions;

use App\Models\Subscription;
use App\Services\Payments\PlanChangeService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Self-service plan downgrade (audit item #2), the non-Stripe half.
 * Stripe's own recurring billing applies a scheduled downgrade at its next
 * renewal webhook (see SubscriptionRenewalService::renew()) — but
 * Flutterwave/Paystack/PayPal are one-time charges with no such webhook to
 * hang this off, so this daily sweep is what actually catches a
 * subscription whose current period has ended with a downgrade waiting.
 */
#[Signature('subscriptions:apply-pending-downgrades')]
#[Description('Apply any scheduled plan downgrade whose current billing period has ended')]
class ApplyPendingDowngrades extends Command
{
    public function handle(PlanChangeService $planChanges): void
    {
        $subscriptions = Subscription::query()
            ->whereNotNull('pending_plan_id')
            ->where('gateway', '!=', 'stripe')
            ->where('status', 'active')
            ->whereNotNull('current_period_end')
            ->where('current_period_end', '<=', now())
            ->get();

        foreach ($subscriptions as $subscription) {
            $planChanges->applyPendingChange($subscription, grantCredits: false);
        }

        $this->info("Applied {$subscriptions->count()} scheduled downgrade(s).");
    }
}
