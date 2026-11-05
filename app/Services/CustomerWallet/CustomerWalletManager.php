<?php

namespace App\Services\CustomerWallet;

use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code that moves money in or out of a customer wallet. Every
 * movement locks the wallet row, changes balance_minor and writes one
 * append-only CustomerWalletTransaction, inside a single database
 * transaction — so the history always adds up to the balance.
 *
 * Every method resolves the user to billableUser() first: a team seat
 * spends and sees its account owner's wallet, the same way credits work.
 */
class CustomerWalletManager
{
    public function walletFor(User $user): CustomerWallet
    {
        $owner = $user->billableUser();

        return CustomerWallet::firstOrCreate(
            ['user_id' => $owner->id],
            ['currency' => $this->currencyFor($owner), 'balance_minor' => 0, 'use_for_renewals' => true],
        );
    }

    public function balance(User $user): int
    {
        return $this->walletFor($user)->fresh()->balance_minor;
    }

    /**
     * Whether the wallet can pay $amountMinor of $currency right now. Wallet
     * money is never converted, so a different currency never qualifies.
     */
    public function canPay(User $user, int $amountMinor, string $currency): bool
    {
        $wallet = $this->walletFor($user)->fresh();

        return $amountMinor > 0
            && strtoupper($currency) === $wallet->currency
            && $wallet->balance_minor >= $amountMinor;
    }

    /**
     * Money into the wallet. With an idempotency key, a repeat call for the
     * same key returns the first row instead of crediting twice — used for
     * verified gateway payments, which can arrive by webhook and callback.
     */
    public function credit(
        User $user,
        int $amountMinor,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $note = null,
        ?User $actor = null,
        ?string $idempotencyKey = null,
    ): CustomerWalletTransaction {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('A wallet credit must be a positive amount.');
        }

        if ($idempotencyKey && ($existing = CustomerWalletTransaction::where('idempotency_key', $idempotencyKey)->first())) {
            return $existing;
        }

        try {
            return $this->move($user, $amountMinor, $type, $description, $reference, $note, $actor, $idempotencyKey);
        } catch (UniqueConstraintViolationException $e) {
            // A concurrent call with the same key won the race.
            return CustomerWalletTransaction::where('idempotency_key', $idempotencyKey)->first() ?? throw $e;
        }
    }

    /**
     * Money out of the wallet.
     *
     * @throws InsufficientCustomerWalletBalanceException
     */
    public function debit(
        User $user,
        int $amountMinor,
        string $type,
        string $description,
        ?Model $reference = null,
        ?string $note = null,
        ?User $actor = null,
    ): CustomerWalletTransaction {
        if ($amountMinor <= 0) {
            throw new InvalidArgumentException('A wallet debit must be a positive amount.');
        }

        return $this->move($user, -$amountMinor, $type, $description, $reference, $note, $actor, null);
    }

    protected function move(
        User $user,
        int $signedAmountMinor,
        string $type,
        string $description,
        ?Model $reference,
        ?string $note,
        ?User $actor,
        ?string $idempotencyKey,
    ): CustomerWalletTransaction {
        $wallet = $this->walletFor($user);

        return DB::transaction(function () use ($wallet, $signedAmountMinor, $type, $description, $reference, $note, $actor, $idempotencyKey) {
            $locked = CustomerWallet::whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            $newBalance = $locked->balance_minor + $signedAmountMinor;

            if ($newBalance < 0) {
                throw new InsufficientCustomerWalletBalanceException(
                    "Wallet {$locked->id} has {$locked->balance_minor} {$locked->currency} minor units; ".abs($signedAmountMinor).' needed.'
                );
            }

            $locked->update(['balance_minor' => $newBalance]);

            return CustomerWalletTransaction::create([
                'customer_wallet_id' => $locked->id,
                'user_id' => $locked->user_id,
                'type' => $type,
                'amount_minor' => $signedAmountMinor,
                'balance_after_minor' => $newBalance,
                'currency' => $locked->currency,
                'description' => $description,
                'note' => $note,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'created_by_user_id' => $actor?->id,
                'idempotency_key' => $idempotencyKey,
            ]);
        });
    }

    /**
     * A new wallet opens in the currency of the customer's plan, so plan
     * renewals can be paid from it; otherwise the platform default.
     */
    protected function currencyFor(User $owner): string
    {
        $planCurrency = $owner->activeSubscription?->plan?->currency
            ?? $owner->subscriptions()->latest('id')->first()?->plan?->currency;

        return strtoupper((string) ($planCurrency ?: config('customer_wallet.default_currency', 'USD')));
    }
}
