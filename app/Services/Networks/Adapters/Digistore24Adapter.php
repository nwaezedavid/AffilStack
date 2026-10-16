<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Models\SiteSetting;
use App\Services\Networks\AddsProgramsByHand;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Crypt;
use Throwable;

/**
 * Digistore24 API (https://www.digistore24.com/api/call/{function}): the
 * key goes in the X-DS-API-KEY header and every answer is wrapped in
 * {result: success|error, data}; errors can arrive with HTTP 200.
 *
 * Connecting: with AffilStack's developer key (Admin → Affiliate
 * Networks) the customer approves on Digistore24 (requestApiKey, then
 * retrieveApiKey with the stored token); without one they paste a key.
 * Commissions come from listCommissions, added up per purchase; sub-IDs
 * from listPurchases (sub_ids.sid1). No public marketplace API, so
 * products are added by product ID. Promolink:
 * checkout-ds24.com/redir/{product}/{affiliate}/ with ?sid1= as sub-ID.
 */
class Digistore24Adapter extends BaseAdapter implements AddsProgramsByHand
{
    public const BASE = 'https://www.digistore24.com/api/call/';

    public const DEVELOPER_KEY_SETTING = 'digistore24_developer_key';

    protected function label(): string
    {
        return 'Digistore24';
    }

    protected function client(string $key): PendingRequest
    {
        return $this->http()->withHeaders(['X-DS-API-KEY' => $key]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     *
     * @throws NetworkException
     */
    protected function call(string $key, string $function, array $params = []): array
    {
        $response = $this->send(fn (): Response => $this->client($key)->asForm()->post(self::BASE.$function, $params + ['language' => 'en']));
        $json = (array) $response->json();

        if (($json['result'] ?? '') !== 'success') {
            $code = (int) ($json['code'] ?? 0);
            $message = $this->limit($json['message'] ?? null, 200) ?? 'unknown error';

            throw in_array($code, [2, 5, 10], true)
                ? NetworkException::rejected('Digistore24 didn\'t accept this API key ('.$message.'). Connect again.')
                : new NetworkException('Digistore24 said: '.$message);
        }

        return (array) ($json['data'] ?? []);
    }

    public static function developerKey(): ?string
    {
        $stored = SiteSetting::get(self::DEVELOPER_KEY_SETTING);

        if (! is_string($stored) || $stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($stored);
        } catch (Throwable) {
            return null;
        }
    }

    public static function canApproveInBrowser(): bool
    {
        return static::developerKey() !== null;
    }

    /**
     * Starts the approve-on-Digistore24 flow.
     *
     * @return array{url: string, token: string}
     *
     * @throws NetworkException
     */
    public function requestApproval(string $returnUrl, string $cancelUrl): array
    {
        $developerKey = static::developerKey() ?? throw new NetworkException('Digistore24 approval isn\'t set up yet. Paste an API key instead.');

        $data = $this->call($developerKey, 'requestApiKey', [
            'permissions' => 'read-only',
            'return_url' => $returnUrl,
            'cancel_url' => $cancelUrl,
            'site_url' => url('/'),
            'comment' => 'AffilStack: import your Digistore24 commissions and build tracked links',
        ]);

        $url = (string) ($data['request_url'] ?? '');
        $token = (string) ($data['request_token'] ?? '');

        if (! preg_match('#^https://#i', $url) || $token === '') {
            throw new NetworkException('Digistore24 didn\'t start the approval. Paste an API key instead.');
        }

        return ['url' => $url, 'token' => $token];
    }

    /**
     * The customer's new key once they've approved, or null.
     *
     * @throws NetworkException
     */
    public function retrieveApprovedKey(string $token): ?string
    {
        $developerKey = static::developerKey() ?? throw new NetworkException('Digistore24 approval isn\'t set up.');
        $data = $this->call($developerKey, 'retrieveApiKey', ['token' => $token]);

        return ($data['request_status'] ?? '') === 'completed' && ! empty($data['api_key']) ? (string) $data['api_key'] : null;
    }

    public function verify(array $credentials): array
    {
        $key = trim((string) ($credentials['api_key'] ?? ''));

        if (strlen($key) < 10) {
            throw NetworkException::rejected('Paste a Digistore24 API key (Settings → Account access → API keys).');
        }

        $info = $this->call($key, 'getUserInfo');
        $info = isset($info['user_name']) ? $info : (array) ($info['data'] ?? $info);
        $affiliateId = trim((string) ($info['user_name'] ?? ''));

        if ($affiliateId === '') {
            throw NetworkException::rejected('This Digistore24 key doesn\'t belong to a user account.');
        }

        return [
            'account_id' => $affiliateId,
            'account_name' => $affiliateId,
            'credentials' => ['api_key' => $key],
        ];
    }

    /**
     * Removes AffilStack's key from the customer's Digistore24 account.
     */
    public function unregister(NetworkConnection $connection): void
    {
        try {
            $this->call((string) $connection->credential('api_key'), 'unregister');
        } catch (Throwable) {
            // Best effort: the key is forgotten on our side regardless.
        }
    }

    public function programs(NetworkConnection $connection): array
    {
        $programs = [];

        foreach ($this->purchases($connection, CarbonImmutable::now()->subDays((int) config('networks.initial_sync_days', 90)), CarbonImmutable::now()) as $purchase) {
            $productId = (string) ($purchase['product_id'] ?? $purchase['main_product_id'] ?? '');

            if (ctype_digit($productId) && ! isset($programs[$productId])) {
                $programs[$productId] = $this->productProgram($connection, $productId, $this->limit($purchase['product_name'] ?? $purchase['main_product_name'] ?? null, 190), 'joined');
            }
        }

        return array_values($programs);
    }

    public function manualProgramLabel(): string
    {
        return 'Product ID (the number in the product\'s Digistore24 marketplace page or promolink)';
    }

    public function programByHand(NetworkConnection $connection, string $input): array
    {
        $productId = preg_match('#/redir/(\d+)#', $input, $match) ? $match[1] : trim($input);

        if (! ctype_digit($productId)) {
            throw new NetworkException('A Digistore24 product ID is a number, like 123456.');
        }

        $check = $this->call((string) $connection->credential('api_key'), 'validateAffiliate', [
            'affiliate_name' => $connection->account_id,
            'product_ids' => $productId,
        ]);

        $status = match ((string) ($check['affiliation_status'] ?? 'approved')) {
            'approved' => 'joined',
            'wait_for_approval' => 'pending',
            'no_valid_products' => throw new NetworkException('Digistore24 doesn\'t have a product '.$productId.' open to affiliates.'),
            default => 'available',
        };

        $program = $this->productProgram($connection, $productId, null, $status);
        $program['meta'] = ['invite_url' => $this->url($check['invite_url'] ?? null)];

        return $program;
    }

    /**
     * @return array<string, mixed>
     */
    protected function productProgram(NetworkConnection $connection, string $productId, ?string $name, string $status): array
    {
        return [
            'external_id' => $productId,
            'name' => $name ?? 'Digistore24 product '.$productId,
            'status' => $status,
            'homepage_url' => 'https://www.digistore24.com/product/'.$productId,
            'tracking_url' => 'https://www.checkout-ds24.com/redir/'.$productId.'/'.rawurlencode((string) $connection->account_id).'/',
            'deep_linking' => false,
        ];
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $key = (string) $connection->credential('api_key');
        $byPurchase = [];
        $page = 1;

        do {
            $data = $this->call($key, 'listCommissions', [
                'from' => $from->utc()->format('Y-m-d H:i:s'),
                'to' => $to->utc()->format('Y-m-d H:i:s'),
                'page_no' => $page,
                'page_size' => 500,
            ]);

            foreach ((array) ($data['items'] ?? []) as $item) {
                if (! is_array($item)) {
                    continue;
                }

                $purchase = (string) ($item['purchase_id'] ?? $item['transaction_id'] ?? $item['id'] ?? '');
                $entry = $byPurchase[$purchase] ?? ['cents' => 0, 'currency' => $this->currency($item['currency'] ?? 'EUR', 'EUR'), 'created_at' => $item['created_at'] ?? null, 'payout_at' => $item['schedule_payout_at'] ?? null];
                $entry['cents'] += $this->cents($item['amount'] ?? 0);
                $byPurchase[$purchase] = $entry;
            }

            $page++;
        } while ($page <= (int) ($data['page_count'] ?? 1) && $page <= 50);

        $subIds = [];

        try {
            foreach ($this->purchases($connection, $from, $to) as $purchase) {
                $subIds[(string) ($purchase['id'] ?? $purchase['purchase_id'] ?? '')] = [
                    'sub_id' => $this->limit(data_get($purchase, 'sub_ids.sid1') ?? ($purchase['campaignkey'] ?? null), 120),
                    'product_id' => (string) ($purchase['product_id'] ?? $purchase['main_product_id'] ?? '') ?: null,
                    'product_name' => $this->limit($purchase['product_name'] ?? $purchase['main_product_name'] ?? null, 190),
                ];
            }
        } catch (NetworkException $e) {
            if ($e->credentialsRejected) {
                throw $e;
            }
            // Commissions still import; they just can't be traced to content.
        }

        $earnings = [];

        foreach ($byPurchase as $purchase => $entry) {
            if ($purchase === '') {
                continue;
            }

            $payoutAt = $entry['payout_at'] ? CarbonImmutable::parse($entry['payout_at']) : null;

            $earnings[] = [
                'external_ref' => 'purchase:'.$purchase,
                'amount_cents' => max(0, $entry['cents']),
                'currency' => $entry['currency'],
                'status' => match (true) {
                    $entry['cents'] <= 0 => 'declined',
                    $payoutAt !== null && $payoutAt->isPast() => 'paid',
                    default => 'approved',
                },
                'converted_at' => $this->date($entry['created_at']),
                'sub_id' => $subIds[$purchase]['sub_id'] ?? null,
                'program_external_id' => $subIds[$purchase]['product_id'] ?? null,
                'description' => $subIds[$purchase]['product_name'] ?? null,
            ];
        }

        return $earnings;
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function purchases(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        $key = (string) $connection->credential('api_key');
        $page = 1;

        do {
            $data = $this->call($key, 'listPurchases', [
                'from' => $from->utc()->format('Y-m-d H:i:s'),
                'to' => $to->utc()->format('Y-m-d H:i:s'),
                'page_no' => $page,
                'page_size' => 500,
            ]);

            foreach ((array) ($data['purchase_list'] ?? []) as $purchase) {
                if (is_array($purchase)) {
                    yield $purchase;
                }
            }

            $page++;
        } while ($page <= (int) ($data['page_count'] ?? 1) && $page <= 50);
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        return $program->tracking_url;
    }

    public function withSubId(string $url, string $subId): string
    {
        return $this->withQuery($url, ['sid1' => $this->safeSubId($subId, 100)]);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['checkout-ds24.com', 'digistore24.com']) && str_contains((string) parse_url($url, PHP_URL_PATH), '/redir/');
    }
}
