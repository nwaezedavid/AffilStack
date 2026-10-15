<?php

namespace App\Services\Referrals;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Saves where a partner (or customer referrer) wants commission sent:
 *
 * - paystack_bank — a Nigerian bank account; the account name is looked
 *   up with Paystack, so the name on file is the one the bank holds and
 *   the admin can pay it with one click;
 * - paypal — a PayPal email;
 * - usdt — a USDT wallet on TRC-20 or BEP-20, checked against that
 *   network's address format.
 */
class PayoutMethodService
{
    public function __construct(protected PaystackTransferService $paystack) {}

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function save(User $user, array $input): void
    {
        $networks = config('referrals.usdt_networks');
        $banks = $this->paystack->banks();

        $data = Validator::make($input, [
            'payout_method' => ['required', 'string', Rule::in(array_keys(config('referrals.payout_methods')))],
            'paypal_email' => ['required_if:payout_method,paypal', 'nullable', 'email', 'max:255'],
            'bank_code' => ['required_if:payout_method,paystack_bank', 'nullable', 'string', 'max:20', $banks !== [] ? Rule::in(array_keys($banks)) : 'regex:/^[0-9A-Za-z]{2,20}$/'],
            'account_number' => ['required_if:payout_method,paystack_bank', 'nullable', 'digits:10'],
            'usdt_network' => ['required_if:payout_method,usdt', 'nullable', Rule::in(array_keys($networks))],
            'usdt_address' => ['required_if:payout_method,usdt', 'nullable', 'string', 'max:100'],
        ], [
            'account_number.digits' => 'Nigerian account numbers (NUBAN) have 10 digits.',
            'bank_code.in' => 'Choose your bank from the list.',
        ])->validate();

        $details = match ($data['payout_method']) {
            'paypal' => ['paypal_email' => strtolower(trim($data['paypal_email']))],
            'paystack_bank' => $this->bankDetails((string) $data['bank_code'], (string) $data['account_number'], $banks),
            'usdt' => $this->walletDetails((string) $data['usdt_network'], trim((string) $data['usdt_address'])),
        };

        $user->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $details,
        ]);
    }

    /**
     * @param  array<string, string>  $banks
     * @return array{bank_code: string, bank_name: string, account_number: string, account_name: string, verified: bool}
     */
    protected function bankDetails(string $bankCode, string $accountNumber, array $banks): array
    {
        try {
            $name = $this->paystack->resolveAccountName($accountNumber, $bankCode);
        } catch (RuntimeException $e) {
            throw ValidationException::withMessages(['account_number' => $e->getMessage()]);
        }

        return [
            'bank_code' => $bankCode,
            'bank_name' => $banks[$bankCode] ?? $bankCode,
            'account_number' => $accountNumber,
            'account_name' => $name,
            'verified' => true,
        ];
    }

    /**
     * @return array{network: string, address: string}
     */
    protected function walletDetails(string $network, string $address): array
    {
        $pattern = config("referrals.usdt_networks.{$network}.address");

        if (! is_string($pattern) || preg_match($pattern, $address) !== 1) {
            throw ValidationException::withMessages(['usdt_address' => $network === 'trc20'
                ? 'A TRC-20 address starts with "T" and has 34 characters.'
                : 'A BEP-20 address starts with "0x" followed by 40 letters and numbers.']);
        }

        return ['network' => $network, 'address' => $address];
    }

    /**
     * One line describing a payout destination, for lists and emails.
     *
     * @param  array<string, mixed>|null  $details
     */
    public static function summary(?string $method, ?array $details): string
    {
        $details ??= [];

        return match ($method) {
            'paypal' => 'PayPal · '.($details['paypal_email'] ?? '—'),
            'paystack_bank' => ($details['bank_name'] ?? 'Bank').' · ****'.substr((string) ($details['account_number'] ?? ''), -4).' · '.($details['account_name'] ?? ''),
            'usdt' => 'USDT '.strtoupper((string) ($details['network'] ?? '')).' · '.static::shortAddress((string) ($details['address'] ?? '')),
            'bank_transfer' => ($details['bank_name'] ?? 'Bank transfer').' · '.($details['account_name'] ?? ''),
            default => '—',
        };
    }

    public static function shortAddress(string $address): string
    {
        return strlen($address) > 14 ? substr($address, 0, 6).'…'.substr($address, -6) : $address;
    }
}
