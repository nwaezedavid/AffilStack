<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * Awin Publisher API (https://api.awin.com): a bearer token the publisher
 * creates at ui.awin.com/awin-api. ShareASale publishers were moved to
 * Awin in 2025, so they connect here too. Programmes by relationship
 * (joined, pending, notjoined), transactions in windows of up to 31 days,
 * and the Link Builder for deep links. 20 calls a minute per user.
 * Sub-ID: clickref.
 */
class AwinAdapter extends BaseAdapter
{
    public const BASE = 'https://api.awin.com/';

    protected function label(): string
    {
        return 'Awin';
    }

    protected function client(string $token): PendingRequest
    {
        return $this->http()->withToken($token);
    }

    public function verify(array $credentials): array
    {
        $token = trim((string) ($credentials['api_token'] ?? ''));
        $wanted = trim((string) ($credentials['publisher_id'] ?? ''));

        if (strlen($token) < 10) {
            throw NetworkException::rejected('Paste the API token from ui.awin.com/awin-api.');
        }

        $accounts = collect((array) $this->send(fn () => $this->client($token)->get(self::BASE.'accounts', ['type' => 'publisher']))->json('accounts', []))
            ->filter(fn ($account) => is_array($account) && ($account['accountType'] ?? 'publisher') === 'publisher')
            ->values();

        $account = $wanted !== ''
            ? $accounts->first(fn ($account) => (string) ($account['accountId'] ?? '') === $wanted)
            : $accounts->first();

        if (! $account) {
            throw NetworkException::rejected($wanted !== ''
                ? 'This token can\'t reach Awin publisher account '.$wanted.'. Check the ID or leave it blank.'
                : 'This token isn\'t linked to an Awin publisher account. Create the token while signed in to your publisher account.');
        }

        return [
            'account_id' => (string) $account['accountId'],
            'account_name' => (string) ($account['accountName'] ?? 'Awin publisher'),
            'credentials' => ['api_token' => $token, 'publisher_id' => (string) $account['accountId']],
        ];
    }

    public function programs(NetworkConnection $connection): array
    {
        $programs = [];
        $available = 0;
        $limit = (int) config('networks.max_available_programs', 1000);

        foreach (['joined' => 'joined', 'pending' => 'pending', 'notjoined' => 'available'] as $relationship => $status) {
            $list = (array) $this->send(fn () => $this->client($this->token($connection))
                ->get(self::BASE.'publishers/'.$this->publisher($connection).'/programmes', ['relationship' => $relationship]))->json();

            foreach ($list as $programme) {
                if (! is_array($programme) || empty($programme['id']) || isset($programs[(string) $programme['id']])) {
                    continue;
                }

                if ($status === 'available' && ++$available > $limit) {
                    break;
                }

                $id = (string) $programme['id'];
                $programs[$id] = [
                    'external_id' => $id,
                    'name' => $this->limit($programme['name'] ?? null, 190) ?? 'Awin programme '.$id,
                    'status' => $status,
                    'homepage_url' => $this->url($programme['displayUrl'] ?? null),
                    'tracking_url' => $status === 'joined' ? $this->url($programme['clickThroughUrl'] ?? null) : null,
                    'logo_url' => $this->url($programme['logoUrl'] ?? null),
                    'category' => $this->limit($programme['primarySector'] ?? null, 120),
                    'currency' => isset($programme['currencyCode']) ? $this->currency($programme['currencyCode']) : null,
                    'description' => $this->limit($programme['description'] ?? null, 2000),
                    'deep_linking' => true,
                    'meta' => ['link_status' => $programme['linkStatus'] ?? null, 'region' => data_get($programme, 'primaryRegion.countryCode')],
                ];
            }
        }

        return array_values($programs);
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $earnings = [];

        foreach ($this->windows($from, $to, 31) as [$start, $end]) {
            $list = (array) $this->send(fn () => $this->client($this->token($connection))
                ->get(self::BASE.'publishers/'.$this->publisher($connection).'/transactions/', [
                    'startDate' => $start->utc()->format('Y-m-d\TH:i:s'),
                    'endDate' => $end->utc()->format('Y-m-d\TH:i:s'),
                    'timezone' => 'UTC',
                ]))->json();

            foreach ($list as $transaction) {
                if (! is_array($transaction) || empty($transaction['id'])) {
                    continue;
                }

                $status = strtolower((string) ($transaction['commissionStatus'] ?? 'pending'));

                $earnings[(string) $transaction['id']] = [
                    'external_ref' => (string) $transaction['id'],
                    'amount_cents' => abs($this->cents(data_get($transaction, 'commissionAmount.amount', 0))),
                    'currency' => $this->currency(data_get($transaction, 'commissionAmount.currency', 'USD')),
                    'status' => match (true) {
                        in_array($status, ['declined', 'deleted'], true) => 'declined',
                        (bool) ($transaction['paidToPublisher'] ?? false) => 'paid',
                        $status === 'approved' => 'approved',
                        default => 'pending',
                    },
                    'converted_at' => $this->date($transaction['transactionDate'] ?? null),
                    'sub_id' => $this->limit(data_get($transaction, 'clickRefs.clickRef'), 120),
                    'program_external_id' => isset($transaction['advertiserId']) ? (string) $transaction['advertiserId'] : null,
                    'description' => null,
                ];
            }
        }

        return array_values($earnings);
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        $body = ['advertiserId' => (int) $program->external_id];

        if ($destination !== null) {
            $body['destinationUrl'] = $destination;
        }

        $response = $this->send(fn () => $this->client($this->token($connection))
            ->post(self::BASE.'publishers/'.$this->publisher($connection).'/linkbuilder/generate', $body));

        return $this->url($response->json('url')) ?? $program->tracking_url;
    }

    public function withSubId(string $url, string $subId): string
    {
        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        if (array_key_exists('clickref', $query)) {
            return $url;
        }

        // Awin wants clickref ahead of the destination ("ued").
        $destination = $query['ued'] ?? null;
        unset($query['ued']);
        $query['clickref'] = $this->safeSubId($subId, 50);

        if ($destination !== null) {
            $query['ued'] = $destination;
        }

        return strtok($url, '?').'?'.http_build_query($query);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['awin1.com', 'tidd.ly', 'shareasale.com']);
    }

    protected function token(NetworkConnection $connection): string
    {
        return (string) $connection->credential('api_token');
    }

    protected function publisher(NetworkConnection $connection): string
    {
        return rawurlencode((string) ($connection->credential('publisher_id') ?: $connection->account_id));
    }
}
