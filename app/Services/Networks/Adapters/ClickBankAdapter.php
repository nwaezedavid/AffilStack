<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Networks\AddsProgramsByHand;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * ClickBank REST API 1.3 (https://api.clickbank.com/rest/1.3/): the API
 * key goes in the Authorization header as-is. Commissions come from
 * orders2/list (role AFFILIATE; 100 a page, "Page" header, 206 while more
 * pages exist); refunds and chargebacks void the sale with the same
 * receipt. ClickBank has no marketplace API, so programs are the vendors
 * the customer has sold for plus any they add by vendor nickname.
 * HopLink: hop.clickbank.net/?affiliate=&vendor=; sub-ID: tid.
 */
class ClickBankAdapter extends BaseAdapter implements AddsProgramsByHand
{
    public const BASE = 'https://api.clickbank.com/rest/1.3/';

    protected function label(): string
    {
        return 'ClickBank';
    }

    protected function client(string $key): PendingRequest
    {
        return $this->http()->withHeaders(['Authorization' => $key]);
    }

    public function verify(array $credentials): array
    {
        $key = trim((string) ($credentials['api_key'] ?? ''));

        if (! str_starts_with($key, 'API-') || strlen($key) < 12) {
            throw NetworkException::rejected('Paste the ClickBank API key that starts with "API-" (Settings → API Management).');
        }

        $nicknames = $this->nicknames($this->send(fn () => $this->client($key)->get(self::BASE.'quickstats/accounts'))->json());

        if ($nicknames === []) {
            throw NetworkException::rejected('This key can\'t read any ClickBank account. Give it "Orders/Tickets API Read" for your affiliate nickname.');
        }

        return [
            'account_id' => $nicknames[0],
            'account_name' => $nicknames[0],
            'credentials' => ['api_key' => $key],
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function nicknames(mixed $data): array
    {
        $found = [];

        array_walk_recursive($data, function ($value, $key) use (&$found) {
            if (is_string($value) && ($key === 'nickName' || $key === 'nickname' || is_int($key)) && preg_match('/^[A-Za-z0-9]{3,20}$/', $value)) {
                $found[] = strtolower($value);
            }
        });

        if ($found === [] && is_string($data) && preg_match('/^[A-Za-z0-9]{3,20}$/', $data)) {
            $found[] = strtolower($data);
        }

        return array_values(array_unique($found));
    }

    public function programs(NetworkConnection $connection): array
    {
        $vendors = [];

        foreach ($this->orders($connection, CarbonImmutable::now()->subDays((int) config('networks.initial_sync_days', 90)), CarbonImmutable::now()) as $order) {
            $vendor = strtolower(trim((string) ($order['vendor'] ?? '')));

            if ($vendor !== '' && ! isset($vendors[$vendor])) {
                $vendors[$vendor] = $this->vendorProgram($connection, $vendor, $this->limit(data_get($order, 'lineItemData.0.productTitle'), 190));
            }
        }

        return array_values($vendors);
    }

    public function manualProgramLabel(): string
    {
        return 'Vendor nickname (shown on the product in the ClickBank marketplace)';
    }

    public function programByHand(NetworkConnection $connection, string $input): array
    {
        $vendor = strtolower(trim($input));

        if (! preg_match('/^[a-z0-9]{3,20}$/', $vendor)) {
            throw new NetworkException('A ClickBank vendor nickname is 3–20 letters or numbers, like "mitolyn".');
        }

        return $this->vendorProgram($connection, $vendor, null);
    }

    /**
     * @return array<string, mixed>
     */
    protected function vendorProgram(NetworkConnection $connection, string $vendor, ?string $title): array
    {
        return [
            'external_id' => $vendor,
            'name' => $title ?? $vendor,
            'advertiser_name' => $vendor,
            'status' => 'joined',
            'tracking_url' => 'https://hop.clickbank.net/?'.http_build_query(['affiliate' => $connection->account_id, 'vendor' => $vendor]),
            'currency' => 'USD',
            'deep_linking' => false,
        ];
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $sales = [];
        $voided = [];

        foreach ($this->orders($connection, $from, $to) as $order) {
            $receipt = trim((string) ($order['receipt'] ?? ''));
            $type = strtoupper((string) ($order['transactionType'] ?? ''));

            if ($receipt === '' || str_starts_with($type, 'TEST')) {
                continue;
            }

            if (in_array($type, ['RFND', 'CGBK'], true)) {
                $voided[$receipt] = true;

                continue;
            }

            if (! in_array($type, ['SALE', 'BILL'], true)) {
                continue;
            }

            $ref = $type === 'BILL' ? $receipt.':'.$this->date($order['transactionTime'] ?? null) : $receipt;
            $cents = 0;

            foreach ($this->list($order['lineItemData'] ?? []) as $item) {
                $cents += $this->cents($item['accountAmount'] ?? 0);
            }

            $sales[$ref] = [
                'external_ref' => $ref,
                'receipt' => $receipt,
                'amount_cents' => abs($cents),
                'currency' => $this->currency($order['currency'] ?? 'USD'),
                'status' => 'approved',
                'converted_at' => $this->date($order['transactionTime'] ?? null),
                'sub_id' => $this->limit($order['trackingId'] ?? null, 120),
                'program_external_id' => strtolower((string) ($order['vendor'] ?? '')) ?: null,
                'description' => $this->limit(data_get($this->list($order['lineItemData'] ?? []), '0.productTitle'), 190),
            ];
        }

        return array_values(array_map(function (array $sale) use ($voided) {
            if (isset($voided[$sale['receipt']])) {
                $sale['status'] = 'declined';
            }
            unset($sale['receipt']);

            return $sale;
        }, $sales));
    }

    /**
     * @return \Generator<int, array<string, mixed>>
     */
    protected function orders(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): \Generator
    {
        $client = $this->client((string) $connection->credential('api_key'));

        foreach ($this->windows($from, $to, 31) as [$start, $end]) {
            $page = 1;

            do {
                $response = $this->send(fn () => $client->withHeaders(['Page' => (string) $page])->get(self::BASE.'orders2/list', [
                    'startDate' => $start->toDateString(),
                    'endDate' => $end->toDateString(),
                    'role' => 'AFFILIATE',
                ]));

                foreach ($this->list($response->json('orderData') ?? []) as $order) {
                    yield $order;
                }

                $page++;
            } while ($response->status() === 206 && $page <= 100);
        }
    }

    /**
     * ClickBank returns a single object instead of a list when there's
     * exactly one item.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function list(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [];
        }

        return array_is_list($value) ? array_values(array_filter($value, 'is_array')) : [$value];
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        return $program->tracking_url;
    }

    public function withSubId(string $url, string $subId): string
    {
        return $this->withQuery($url, ['tid' => $this->safeSubId($subId, 100)]);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['hop.clickbank.net']);
    }
}
