<?php

namespace App\Services\Referrals;

use App\Services\Payments\PaystackGateway;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

/**
 * Paystack Transfers for partner payouts into Nigerian bank accounts:
 * the bank list, account-name lookup (so the name on file is the one the
 * bank holds), and sending a transfer from the Paystack balance. Uses the
 * secret key saved in Payment Gateways → Paystack.
 *
 * Paystack may finish a transfer later: "pending" here becomes final with
 * the transfer.success / transfer.failed / transfer.reversed webhook — see
 * PayoutDisbursementService::handlePaystackTransferEvent().
 */
class PaystackTransferService
{
    public function __construct(protected PaystackGateway $gateway) {}

    public function isConfigured(): bool
    {
        return $this->gateway->hasSecretKey();
    }

    /**
     * Nigerian banks Paystack can pay into, as code => name. Cached for a
     * day; an empty list means Paystack isn't set up or didn't answer.
     *
     * @return array<string, string>
     */
    public function banks(): array
    {
        if (! $this->isConfigured()) {
            return [];
        }

        $cached = Cache::get('paystack:banks:ng');

        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        try {
            $banks = [];
            $cursor = null;

            for ($page = 0; $page < 10; $page++) {
                $response = $this->client()->get('/bank', array_filter([
                    'country' => 'nigeria',
                    'currency' => 'NGN',
                    'perPage' => 100,
                    'use_cursor' => 'true',
                    'next' => $cursor,
                ]));

                if ($response->failed() || $response->json('status') !== true) {
                    break;
                }

                foreach ((array) $response->json('data', []) as $bank) {
                    if (filled($bank['code'] ?? null) && filled($bank['name'] ?? null) && ($bank['active'] ?? true) && ! ($bank['is_deleted'] ?? false)) {
                        $banks[(string) $bank['code']] = (string) $bank['name'];
                    }
                }

                $cursor = $response->json('meta.next');

                if (! $cursor) {
                    break;
                }
            }
        } catch (Throwable) {
            return [];
        }

        asort($banks, SORT_NATURAL | SORT_FLAG_CASE);

        if ($banks !== []) {
            Cache::put('paystack:banks:ng', $banks, now()->addDay());
        }

        return $banks;
    }

    /**
     * The account holder's name as the bank has it.
     *
     * @throws RuntimeException when the account can't be found
     */
    public function resolveAccountName(string $accountNumber, string $bankCode): string
    {
        if (! $this->isConfigured()) {
            throw new RuntimeException('Bank account checks are unavailable right now. Please try again later or choose another payout method.');
        }

        try {
            $response = $this->client()->get('/bank/resolve', ['account_number' => $accountNumber, 'bank_code' => $bankCode]);
        } catch (Throwable) {
            throw new RuntimeException('We couldn\'t reach the bank to check this account. Please try again in a moment.');
        }

        $name = trim((string) $response->json('data.account_name'));

        if ($response->failed() || $response->json('status') !== true || $name === '') {
            throw new RuntimeException('We couldn\'t find that account. Check the account number and bank, then try again.');
        }

        return $name;
    }

    /**
     * @return array{success: bool, status: string, message: string, reference?: string, transfer_code?: string}
     */
    public function transfer(string $accountName, string $accountNumber, string $bankCode, int $amountKobo, string $reference, string $reason): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'status' => 'failed', 'message' => 'Add your Paystack secret key in Payment Gateways first.'];
        }

        $recipient = $this->client()->post('/transferrecipient', [
            'type' => 'nuban',
            'name' => $accountName,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => 'NGN',
        ]);

        $recipientCode = (string) $recipient->json('data.recipient_code');

        if ($recipient->failed() || $recipient->json('status') !== true || $recipientCode === '') {
            return ['success' => false, 'status' => 'failed', 'message' => 'Paystack couldn\'t set up this bank account: '.($recipient->json('message') ?: 'unknown error').'.'];
        }

        $response = $this->client()->post('/transfer', [
            'source' => 'balance',
            'amount' => $amountKobo,
            'recipient' => $recipientCode,
            'reference' => $reference,
            'reason' => $reason,
            'currency' => 'NGN',
        ]);

        $status = (string) $response->json('data.status');

        if ($response->failed() || $response->json('status') !== true) {
            return ['success' => false, 'status' => 'failed', 'message' => 'Paystack refused the transfer: '.($response->json('message') ?: 'unknown error').'.'];
        }

        if ($status === 'otp') {
            return ['success' => false, 'status' => 'otp', 'message' => 'Paystack is asking for an OTP for this transfer. Turn off "Confirm transfers before sending" in your Paystack dashboard (Settings → Preferences), finish this one there, then mark it paid here.', 'reference' => $reference, 'transfer_code' => (string) $response->json('data.transfer_code')];
        }

        return [
            'success' => in_array($status, ['success', 'pending', 'received', 'processing'], true),
            'status' => $status ?: 'pending',
            'message' => (string) ($response->json('message') ?: 'Transfer queued.'),
            'reference' => $reference,
            'transfer_code' => (string) $response->json('data.transfer_code'),
        ];
    }

    protected function client(): PendingRequest
    {
        return Http::withToken($this->gateway->apiSecretKey())
            ->baseUrl($this->gateway->apiBaseUrl())
            ->acceptJson()
            ->timeout(20);
    }
}
