<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * impact.com Partner API (https://api.impact.com/Mediapartners/{SID}/…):
 * HTTP Basic with the Account SID and an auth token. Joined programs come
 * from Campaigns (with payout terms from Contracts), commissions from
 * Actions (in windows of up to 45 days by last update, so status changes
 * are picked up), and deep links from the TrackingLinks endpoint. Impact
 * doesn't share its marketplace through the API. Sub-ID: subId1.
 */
class ImpactAdapter extends BaseAdapter
{
    public const BASE = 'https://api.impact.com/Mediapartners/';

    protected function label(): string
    {
        return 'Impact';
    }

    protected function forbiddenMeansBadKeys(): bool
    {
        // 403 means the keys work but this token isn't allowed the call.
        return false;
    }

    protected function client(string $sid, string $token): PendingRequest
    {
        return $this->http()->withBasicAuth($sid, $token);
    }

    protected function base(string $sid): string
    {
        return self::BASE.rawurlencode($sid).'/';
    }

    public function verify(array $credentials): array
    {
        $sid = trim((string) ($credentials['account_sid'] ?? ''));
        $token = trim((string) ($credentials['auth_token'] ?? ''));

        if (! preg_match('/^[A-Za-z0-9]{10,64}$/', $sid) || $token === '') {
            throw NetworkException::rejected('Enter your Impact Account SID (it starts with "IR") and an auth token.');
        }

        $company = $this->send(fn () => $this->client($sid, $token)->get($this->base($sid).'CompanyInformation'))->json();

        return [
            'account_id' => $sid,
            'account_name' => (string) ($company['CompanyName'] ?? 'Impact account'),
            'credentials' => ['account_sid' => $sid, 'auth_token' => $token],
        ];
    }

    public function programs(NetworkConnection $connection): array
    {
        $terms = $this->contractTerms($connection);
        $programs = [];

        foreach ($this->pages($connection, 'Campaigns', [], 'Campaigns') as $campaign) {
            $id = (string) ($campaign['CampaignId'] ?? '');

            if ($id === '') {
                continue;
            }

            $programs[] = [
                'external_id' => $id,
                'name' => $this->limit($campaign['CampaignName'] ?? null, 190) ?? 'Program '.$id,
                'advertiser_name' => $this->limit($campaign['AdvertiserName'] ?? null, 190),
                'status' => strcasecmp((string) ($campaign['ContractStatus'] ?? 'Active'), 'Active') === 0 ? 'joined' : 'pending',
                'homepage_url' => $this->url($campaign['CampaignUrl'] ?? $campaign['AdvertiserUrl'] ?? null),
                'tracking_url' => $this->url($campaign['TrackingLink'] ?? null),
                'currency' => $terms[$id]['currency'] ?? null,
                'commission' => $terms[$id]['commission'] ?? null,
                'description' => $this->limit($campaign['CampaignDescription'] ?? null, 2000),
                'deep_linking' => filter_var($campaign['AllowsDeeplinking'] ?? false, FILTER_VALIDATE_BOOL),
                'meta' => ['deeplink_domains' => array_values((array) ($campaign['DeeplinkDomains'] ?? []))],
            ];
        }

        return $programs;
    }

    /**
     * @return array<string, array{currency: ?string, commission: ?string}>
     */
    protected function contractTerms(NetworkConnection $connection): array
    {
        $terms = [];

        try {
            foreach ($this->pages($connection, 'Contracts', ['Status' => 'ACTIVE'], 'Contracts') as $contract) {
                $payout = collect((array) data_get($contract, 'Terms.EventPayouts', []))->first();
                $rate = is_array($payout) ? trim((string) ($payout['DefaultPayoutRate'] ?? '')) : '';
                $amount = is_array($payout) ? trim((string) ($payout['DefaultPayout'] ?? '')) : '';
                $currency = $this->currency(data_get($contract, 'Terms.Currency'), '');

                $terms[(string) ($contract['CampaignId'] ?? '')] = [
                    'currency' => $currency !== '' ? $currency : null,
                    'commission' => match (true) {
                        $rate !== '' && (float) $rate > 0 => rtrim(rtrim($rate, '0'), '.').'% per '.strtolower((string) ($payout['EventTypeName'] ?? 'sale')),
                        $amount !== '' && (float) $amount > 0 => trim($currency.' '.$amount).' per '.strtolower((string) ($payout['EventTypeName'] ?? 'sale')),
                        default => null,
                    },
                ];
            }
        } catch (NetworkException $e) {
            if ($e->credentialsRejected) {
                throw $e;
            }
            // Payout terms are a nice extra; programs still list without them.
        }

        return $terms;
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $earnings = [];

        foreach ($this->windows($from, $to, 45) as [$start, $end]) {
            $params = ['StartDate' => $start->utc()->format('Y-m-d\TH:i:s\Z'), 'EndDate' => $end->utc()->format('Y-m-d\TH:i:s\Z')];

            foreach ($this->pages($connection, 'Actions', $params, 'Actions') as $action) {
                $id = (string) ($action['Id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $state = strtoupper((string) ($action['State'] ?? 'PENDING'));

                $earnings[$id] = [
                    'external_ref' => $id,
                    'amount_cents' => abs($this->cents($action['Payout'] ?? 0)),
                    'currency' => $this->currency($action['Currency'] ?? 'USD'),
                    'status' => match (true) {
                        $state === 'REVERSED' => 'declined',
                        $state === 'APPROVED' && filled($action['ClearedDate'] ?? null) => 'paid',
                        $state === 'APPROVED' => 'approved',
                        default => 'pending',
                    },
                    'converted_at' => $this->date($action['EventDate'] ?? $action['CreationDate'] ?? null),
                    'sub_id' => $this->limit($action['SubId1'] ?? null, 120),
                    'program_external_id' => isset($action['CampaignId']) ? (string) $action['CampaignId'] : null,
                    'description' => $this->limit($action['CampaignName'] ?? null, 190),
                ];
            }
        }

        return array_values($earnings);
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        if ($destination === null || ! $program->deep_linking) {
            return $program->tracking_url;
        }

        $sid = (string) $connection->credential('account_sid');
        $query = http_build_query(['Type' => 'Regular', 'DeepLink' => $destination]);

        $response = $this->send(fn () => $this->client($sid, (string) $connection->credential('auth_token'))
            ->asForm()
            ->post($this->base($sid).'Programs/'.rawurlencode($program->external_id).'/TrackingLinks?'.$query));

        return $this->url($response->json('TrackingURL')) ?? $program->tracking_url;
    }

    public function withSubId(string $url, string $subId): string
    {
        return $this->withQuery($url, ['subId1' => $this->safeSubId($subId, 100)]);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['sjv.io', 'pxf.io', '7eer.net', 'ojrq.net', 'eqjw.net', 'audw.net', 'pvxt.net', 'snlv.net'])
            || (bool) preg_match('#^/c/\d+/\d+/\d+/?$#', (string) parse_url($url, PHP_URL_PATH));
    }

    /**
     * Every item of a paged Impact list.
     *
     * @param  array<string, string>  $params
     * @return \Generator<int, array<string, mixed>>
     */
    protected function pages(NetworkConnection $connection, string $resource, array $params, string $key): \Generator
    {
        $sid = (string) $connection->credential('account_sid');
        $client = $this->client($sid, (string) $connection->credential('auth_token'));
        $page = 1;

        do {
            $data = $this->send(fn () => $client->get($this->base($sid).$resource, $params + ['Page' => $page, 'PageSize' => 1000]))->json();

            foreach ((array) ($data[$key] ?? []) as $item) {
                if (is_array($item)) {
                    yield $item;
                }
            }

            $page++;
            $more = filled($data['@nextpageuri'] ?? null) && $page <= (int) ($data['@numpages'] ?? $page) && $page <= 50;
        } while ($more);
    }
}
