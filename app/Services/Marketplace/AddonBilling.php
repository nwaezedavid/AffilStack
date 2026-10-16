<?php

namespace App\Services\Marketplace;

use App\Models\Addon;
use App\Models\AddonInstall;
use App\Models\DeveloperEarning;
use App\Models\PaymentMethod;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Notifications\AddonSubscriptionEnded;
use App\Notifications\AddonSubscriptionPaymentFailed;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayManager;
use App\Support\Marketplace\MarketplaceSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Monthly subscriptions to paid add-ons: the first payment through the
 * normal checkout, renewals charged to the saved card (with a "Pay now"
 * link and a few days' grace when that fails), and the developer's share
 * of every payment.
 */
class AddonBilling
{
    public const TYPE = 'addon_subscription';

    public function __construct(protected PaymentGatewayManager $gateways, protected AddonInstaller $installer) {}

    /**
     * Starts the checkout for a month of a paid add-on.
     *
     * @return array{link: string, transaction: PaymentTransaction}
     */
    public function startCheckout(User $user, Addon $addon, PaymentGateway $gateway): array
    {
        $version = $addon->liveVersion;

        if (! $addon->isListed() || ! $version?->isPaid()) {
            throw new RuntimeException('This add-on is not for sale right now.');
        }

        $install = AddonInstall::query()->where('addon_id', $addon->id)->where('user_id', $user->id)->first();

        if ($install && $install->status === 'active' && $install->isUsable()) {
            throw new RuntimeException('You already have this add-on.');
        }

        $install = $this->installer->install($user, $addon, $install?->scopes ?? [], 'pending_payment');
        $price = $install->status === 'past_due' && $install->price_cents > 0 ? $install->price_cents : $version->price_cents;

        $checkout = $gateway->initiateOneTimeCheckout($user, $price, 'USD', $version->name.' add-on — 1 month', [
            'user_id' => $user->id,
            'addon_install_id' => $install->id,
            'type' => self::TYPE,
            'save_card' => true,
        ]);

        $transaction = PaymentTransaction::create([
            'user_id' => $user->id,
            'addon_install_id' => $install->id,
            'type' => self::TYPE,
            'gateway' => $gateway->key(),
            'tx_ref' => $checkout['tx_ref'],
            'amount_cents' => $checkout['amount_cents'],
            'currency' => $checkout['currency'],
            'credited_amount_cents' => $price,
            'status' => 'pending',
        ]);

        return ['link' => $checkout['link'], 'transaction' => $transaction];
    }

    /**
     * A payment for an add-on succeeded: start or extend the month and
     * record the developer's share. Runs inside PaymentProcessor's
     * database transaction.
     */
    public function activate(PaymentTransaction $transaction, ?PaymentMethod $method = null): void
    {
        $install = $transaction->addonInstall()->lockForUpdate()->first();

        if (! $install) {
            Log::error('Add-on payment succeeded but the install could not be found', ['tx_ref' => $transaction->tx_ref]);

            return;
        }

        $wasActive = in_array($install->status, ['active', 'past_due'], true);
        $start = $install->current_period_end?->isFuture() && $wasActive ? $install->current_period_end : now();

        $install->update([
            'status' => 'active',
            'price_cents' => (int) ($transaction->credited_amount_cents ?: $install->price_cents),
            'payment_method_id' => $method?->id ?? $install->payment_method_id,
            'current_period_end' => $start->copy()->addMonthNoOverflow(),
            'cancel_at_period_end' => false,
            'renewal_attempted_at' => null,
            'installed_at' => $install->installed_at ?? now(),
            'uninstalled_at' => null,
        ]);

        if (! $wasActive) {
            $install->addon()->increment('installs_count');
        }

        $this->installer->provision($install);
        $this->recordEarning($transaction, $install);
    }

    public function recordEarning(PaymentTransaction $transaction, AddonInstall $install): void
    {
        $developer = $install->addon?->developer;

        if (! $developer || DeveloperEarning::where('payment_transaction_id', $transaction->id)->where('type', 'sale')->exists()) {
            return;
        }

        // Earnings are in US dollars (the add-on's price), whatever
        // currency the customer paid in.
        $gross = (int) ($transaction->credited_amount_cents ?: $install->price_cents);
        $share = $developer->sharePercent();

        DeveloperEarning::create([
            'developer_profile_id' => $developer->id,
            'addon_id' => $install->addon_id,
            'addon_install_id' => $install->id,
            'payment_transaction_id' => $transaction->id,
            'type' => 'sale',
            'gross_cents' => $gross,
            'share_percent' => $share,
            'amount_cents' => (int) round($gross * $share / 100),
            'currency' => 'USD',
            'status' => 'pending',
            'available_at' => now()->addDays(MarketplaceSettings::holdDays()),
            'occurred_at' => now(),
        ]);
    }

    /**
     * Charges every paid add-on that renews within the next day, and ends
     * the ones cancelled or unpaid.
     *
     * @return array{renewed: int, failed: int, ended: int}
     */
    public function renewDue(): array
    {
        $stats = ['renewed' => 0, 'failed' => 0, 'ended' => 0];

        AddonInstall::query()
            ->where('status', 'active')
            ->where('cancel_at_period_end', true)
            ->where('current_period_end', '<=', now())
            ->each(function (AddonInstall $install) use (&$stats) {
                $this->end($install);
                $stats['ended']++;
            });

        AddonInstall::query()
            ->where('status', 'active')
            ->where('price_cents', '>', 0)
            ->where('cancel_at_period_end', false)
            ->where('current_period_end', '<=', now()->addDay())
            ->where(fn ($query) => $query->whereNull('renewal_attempted_at')->orWhere('renewal_attempted_at', '<', now()->subHours(20)))
            ->with(['addon.liveVersion', 'user', 'paymentMethod'])
            ->each(function (AddonInstall $install) use (&$stats) {
                $this->renew($install) ? $stats['renewed']++ : $stats['failed']++;
            });

        AddonInstall::query()
            ->where('status', 'past_due')
            ->where('current_period_end', '<=', now()->subDays((int) config('marketplace.grace_days')))
            ->each(function (AddonInstall $install) use (&$stats) {
                $this->end($install);
                $stats['ended']++;
            });

        return $stats;
    }

    public function renew(AddonInstall $install): bool
    {
        $install->update(['renewal_attempted_at' => now()]);

        // An add-on that's no longer for sale isn't renewed.
        if (! $install->addon?->isListed() || ! $install->user || $install->user->is_suspended) {
            $install->update(['cancel_at_period_end' => true]);

            return false;
        }

        $method = $install->paymentMethod ?? $install->user->defaultPaymentMethod();
        $result = ['success' => false, 'message' => 'No saved card to charge.'];

        if ($method instanceof PaymentMethod && $method->type === 'card' && $this->gateways->has($method->gateway)) {
            try {
                $result = $this->gateways->get($method->gateway)->chargeSavedToken($method, $install->price_cents, 'USD', ($install->addon->liveVersion?->name ?? 'Add-on').' add-on — monthly renewal');
            } catch (Throwable $e) {
                report($e);
                $result = ['success' => false, 'message' => 'The card could not be charged.'];
            }
        }

        if (! ($result['success'] ?? false)) {
            $install->update(['status' => 'past_due']);
            $install->user->notify(new AddonSubscriptionPaymentFailed($install, (string) ($result['message'] ?? '')));

            return false;
        }

        DB::transaction(function () use ($install, $method, $result) {
            $transaction = PaymentTransaction::create([
                'user_id' => $install->user_id,
                'addon_install_id' => $install->id,
                'type' => self::TYPE,
                'gateway' => $method->gateway,
                'tx_ref' => 'affilstack_addon_renewal_'.Str::uuid(),
                'gateway_tx_id' => $result['reference'] ?? null,
                'gateway_reference' => $result['reference'] ?? null,
                'amount_cents' => $install->price_cents,
                'currency' => 'USD',
                'credited_amount_cents' => $install->price_cents,
                'status' => 'successful',
                'raw_payload' => $result['raw'] ?? null,
                'processed_at' => now(),
            ]);

            $method->update(['last_used_at' => now()]);
            $this->activate($transaction, $method);
        });

        return true;
    }

    /**
     * Cancel: a paid add-on keeps working until the end of the month it
     * was paid for; a free one is removed straight away.
     */
    public function cancel(AddonInstall $install): void
    {
        if ($install->isPaid() && $install->status === 'active' && $install->current_period_end?->isFuture()) {
            $install->update(['cancel_at_period_end' => true]);

            return;
        }

        $this->installer->uninstall($install);
    }

    public function resume(AddonInstall $install): void
    {
        if ($install->status === 'active' && $install->current_period_end?->isFuture()) {
            $install->update(['cancel_at_period_end' => false]);
        }
    }

    public function end(AddonInstall $install): void
    {
        $this->installer->uninstall($install, 'expired');
        $install->user?->notify(new AddonSubscriptionEnded($install));
    }

    /**
     * A payment was refunded or charged back: the customer loses the
     * add-on and the developer's share is taken back.
     */
    public function reverse(PaymentTransaction $transaction, string $status): void
    {
        if ($install = $transaction->addonInstall) {
            $this->installer->uninstall($install, 'expired');
        }

        $earning = DeveloperEarning::where('payment_transaction_id', $transaction->id)->where('type', 'sale')->first();

        if (! $earning) {
            return;
        }

        if ($earning->referral_payout_id === null && in_array($earning->status, ['pending', 'approved'], true)) {
            $earning->update(['status' => 'reversed']);

            return;
        }

        if (DeveloperEarning::where('payment_transaction_id', $transaction->id)->whereIn('type', ['refund', 'chargeback'])->exists()) {
            return;
        }

        DeveloperEarning::create([
            'developer_profile_id' => $earning->developer_profile_id,
            'addon_id' => $earning->addon_id,
            'addon_install_id' => $earning->addon_install_id,
            'payment_transaction_id' => $transaction->id,
            'type' => $status === 'charged_back' ? 'chargeback' : 'refund',
            'gross_cents' => -$earning->gross_cents,
            'share_percent' => $earning->share_percent,
            'amount_cents' => -$earning->amount_cents,
            'currency' => $earning->currency,
            'status' => 'approved',
            'available_at' => now(),
            'occurred_at' => now(),
        ]);
    }
}
