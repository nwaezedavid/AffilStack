<?php

namespace App\Services\Referrals;

use App\Models\ReferralPayout;
use App\Models\User;
use App\Services\Payments\FlutterwaveGateway;
use App\Services\Payments\PayPalGateway;
use App\Support\ReferralProgram;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

/**
 * Automatic disbursement for the affiliate payout wallet (audit item #5) —
 * the user's own choice of rail: Flutterwave Transfers for Africa (bank
 * payouts, settled in NGN), PayPal Payouts for everyone else. An admin
 * triggers this per payout from the Referral Payouts table (never a
 * background sweep — real money moving deserves a human clicking the
 * button); on success it debits PayoutWalletService's ledger and completes
 * the payout exactly like ReferralPayoutService::processPayout() always
 * has, just with the gateway's own transfer id as the reference instead of
 * one typed in by hand.
 *
 * Deliberately narrow about what it will auto-disburse: a PayPal method
 * needs only the email already on file, but a bank_transfer needs a
 * Flutterwave bank_code the affiliate may not have supplied yet (it's
 * optional on the payout-details form — see dashboard/referrals/index.blade.php
 * — because it's only ever needed for this automatic path, not the existing
 * manual "mark paid" flow). Anything this can't handle throws, so the
 * Filament action can fall back to telling the admin to use "Mark paid"
 * manually instead.
 */
class PayoutDisbursementService
{
    public function __construct(
        protected PayoutWalletService $wallet,
        protected ReferralPayoutService $payouts,
        protected FlutterwaveGateway $flutterwave,
        protected PayPalGateway $paypal,
        protected PaystackTransferService $paystack,
    ) {}

    public function canAutoDisburse(ReferralPayout $payout): bool
    {
        return match ($payout->payout_method) {
            'paypal' => filled($payout->payout_details['paypal_email'] ?? null),
            'paystack_bank' => in_array($payout->currency, ['NGN', 'USD'], true)
                && filled($payout->payout_details['bank_code'] ?? null)
                && filled($payout->payout_details['account_number'] ?? null)
                && $this->paystack->isConfigured(),
            'bank_transfer' => $payout->currency === 'NGN' && filled($payout->payout_details['bank_code'] ?? null) && filled($payout->payout_details['account_number'] ?? null),
            default => false,
        };
    }

    public function disburse(ReferralPayout $payout, User $admin): void
    {
        if (! $payout->isRequested()) {
            throw new InvalidArgumentException('Only a requested payout can be disbursed.');
        }

        if (! $this->canAutoDisburse($payout)) {
            throw new InvalidArgumentException($payout->payout_method === 'bank_transfer'
                ? 'This affiliate\'s bank details are missing a Flutterwave bank code, or the payout currency isn\'t NGN — use "Mark paid" once you\'ve sent it manually.'
                : 'This payout method can\'t be auto-disbursed — use "Mark paid" once you\'ve sent it manually.');
        }

        if ($payout->payout_method === 'paystack_bank') {
            $this->sendViaPaystack($payout, $admin);

            return;
        }

        // One disbursement per currency at a time, so two payouts can't both
        // pass the balance check against the same wallet money.
        Cache::lock('payout-wallet-disburse:'.$payout->currency, 120)->block(15, function () use ($payout, $admin) {
            $this->sendClaimed($payout, $admin);
        });
    }

    protected function sendClaimed(ReferralPayout $payout, User $admin): void
    {
        if (! $this->wallet->hasSufficientBalance($payout->currency, $payout->amount_cents)) {
            $balance = number_format($this->wallet->balance($payout->currency) / 100, 2);
            throw new InvalidArgumentException("Insufficient {$payout->currency} wallet balance (currently {$balance}) — top up the wallet first.");
        }

        // Claim it before any money moves: a double-click, a second admin,
        // or a replayed Livewire request now finds it already "disbursing"
        // instead of sending a second transfer.
        $claimed = ReferralPayout::whereKey($payout->id)->where('status', 'requested')->update(['status' => 'disbursing']);

        if ($claimed !== 1) {
            throw new InvalidArgumentException('This payout is already being processed.');
        }

        // Deterministic per payout, so even a retry after an unclear
        // failure is rejected by PayPal/Flutterwave as a duplicate rather
        // than paid twice.
        $reference = 'affilstack_payout_'.$payout->id;

        try {
            $result = $payout->payout_method === 'paypal'
                ? $this->paypal->payout(
                    $payout->payout_details['paypal_email'],
                    $payout->amount_cents,
                    $payout->currency,
                    $reference,
                    'AffilStack affiliate commission payout',
                )
                : $this->flutterwave->transfer(
                    $payout->payout_details['bank_code'],
                    $payout->payout_details['account_number'],
                    $payout->payout_details['account_name'] ?? $payout->user->name,
                    $payout->amount_cents,
                    $payout->currency,
                    $reference,
                    'AffilStack affiliate commission payout',
                );
        } catch (\Throwable $e) {
            // Outcome unknown (timeout mid-request) — leave it "disbursing"
            // for a human to reconcile against the gateway dashboard rather
            // than risk paying twice.
            throw new InvalidArgumentException('The payout request to the gateway did not complete ('.$e->getMessage().'). Check the gateway dashboard for reference '.$reference.' before trying again.');
        }

        if (! $result['success']) {
            ReferralPayout::whereKey($payout->id)->where('status', 'disbursing')->update(['status' => 'requested']);

            throw new InvalidArgumentException($result['message']);
        }

        $gateway = $payout->payout_method === 'paypal' ? 'paypal' : 'flutterwave';

        $this->wallet->recordDisbursement($payout, $gateway, $result['reference']);
        $this->payouts->processPayout($payout, $admin, $result['reference'], 'Auto-disbursed from the payout wallet via '.ucfirst($gateway).'.');
    }

    /**
     * Naira amount (in kobo) a Paystack bank payout sends: a naira balance
     * as-is, a dollar balance converted at the Paystack rate.
     */
    public function paystackAmountKobo(ReferralPayout $payout): int
    {
        return $payout->currency === 'NGN'
            ? (int) $payout->amount_cents
            : (int) round($payout->amount_cents * ReferralProgram::ngnPerUsd());
    }

    /**
     * One-click Paystack transfer to the partner's verified Nigerian bank
     * account, from the Paystack balance (no payout wallet involved).
     * Paystack usually queues the transfer, so the payout stays
     * "disbursing" until its webhook confirms it.
     */
    protected function sendViaPaystack(ReferralPayout $payout, User $admin): void
    {
        $kobo = $this->paystackAmountKobo($payout);

        $claimed = ReferralPayout::whereKey($payout->id)->where('status', 'requested')->update([
            'status' => 'disbursing',
            'processed_by_id' => $admin->id,
            'sent_amount_cents' => $kobo,
            'sent_currency' => 'NGN',
        ]);

        if ($claimed !== 1) {
            throw new InvalidArgumentException('This payout is already being processed.');
        }

        $reference = 'affilstack_payout_'.$payout->id.'_'.now()->timestamp;
        $details = $payout->payout_details;

        try {
            $result = $this->paystack->transfer(
                (string) ($details['account_name'] ?? $payout->user->name),
                (string) $details['account_number'],
                (string) $details['bank_code'],
                $kobo,
                $reference,
                'AffilStack partner commission payout',
            );
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('The transfer request to Paystack did not complete ('.$e->getMessage().'). Check your Paystack dashboard for reference '.$reference.' before trying again.');
        }

        if ($result['status'] === 'otp') {
            ReferralPayout::whereKey($payout->id)->update(['reference' => $reference]);

            throw new InvalidArgumentException($result['message']);
        }

        if (! $result['success']) {
            ReferralPayout::whereKey($payout->id)->where('status', 'disbursing')->update(['status' => 'requested', 'sent_amount_cents' => null, 'sent_currency' => null]);

            throw new InvalidArgumentException($result['message']);
        }

        $note = 'Sent '.ReferralProgram::money($kobo, 'NGN').' via Paystack Transfers'
            .($payout->currency === 'USD' ? ' (converted at ₦'.number_format(ReferralProgram::ngnPerUsd(), 2).' per $1).' : '.');

        if ($result['status'] === 'success') {
            $this->payouts->processPayout($payout->fresh(), $admin, $result['reference'] ?? $reference, $note);

            return;
        }

        ReferralPayout::whereKey($payout->id)->update(['reference' => $result['reference'] ?? $reference, 'note' => $note.' Waiting for Paystack to confirm.']);
    }

    /**
     * Paystack's transfer.success / transfer.failed / transfer.reversed
     * webhook for a payout this app sent.
     *
     * @param  array<string, mixed>  $data
     */
    public function handlePaystackTransferEvent(string $event, array $data): void
    {
        if (! preg_match('/^affilstack_payout_(\d+)(?:_\d+)?$/', (string) ($data['reference'] ?? ''), $match)) {
            return;
        }

        $payout = ReferralPayout::find((int) $match[1]);

        if (! $payout || $payout->status !== 'disbursing') {
            return;
        }

        if ($event === 'transfer.success') {
            $this->payouts->processPayout($payout, null, (string) $data['reference'], $payout->note ? str_replace(' Waiting for Paystack to confirm.', '', $payout->note) : 'Sent via Paystack Transfers.');

            return;
        }

        if (in_array($event, ['transfer.failed', 'transfer.reversed'], true)) {
            $reason = (string) ($data['reason'] ?? $data['gateway_response'] ?? '');

            ReferralPayout::whereKey($payout->id)->where('status', 'disbursing')->update([
                'status' => 'requested',
                'sent_amount_cents' => null,
                'sent_currency' => null,
                'note' => 'Paystack '.($event === 'transfer.failed' ? 'transfer failed' : 'transfer was reversed').($reason !== '' ? ': '.$reason : '').'. Check the partner\'s bank details, then send again or mark paid.',
            ]);
        }
    }
}
