<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;
use SimpleXMLElement;

/**
 * CJ Affiliate: a personal access token plus the publisher's company ID
 * (CID) and website ID (PID). Advertisers (joined and not joined) come
 * from the Advertiser Lookup API (XML), commissions from the Commission
 * Detail GraphQL API (31-day windows; corrections arrive as extra records
 * on the same order, so records are added up per order), and tracked
 * links from Link Search. Sub-ID: sid (reported back as shopperId).
 */
class CjAdapter extends BaseAdapter
{
    public const COMMISSIONS = 'https://commissions.api.cj.com/query';

    public const ADVERTISERS = 'https://advertiser-lookup.api.cj.com/v2/advertiser-lookup';

    public const LINKS = 'https://link-search.api.cj.com/v2/link-search';

    protected function label(): string
    {
        return 'CJ';
    }

    protected function client(string $token): PendingRequest
    {
        return $this->http()->withToken($token);
    }

    public function verify(array $credentials): array
    {
        $token = trim((string) ($credentials['access_token'] ?? ''));
        $cid = trim((string) ($credentials['publisher_cid'] ?? ''));
        $pid = trim((string) ($credentials['website_id'] ?? ''));

        if (strlen($token) < 10 || ! ctype_digit($cid) || ! ctype_digit($pid)) {
            throw NetworkException::rejected('Enter your CJ personal access token, your Company ID (CID) and your Website ID (PID). The IDs are numbers.');
        }

        $xml = $this->xml($this->send(fn () => $this->client($token)->accept('application/xml')
            ->get(self::ADVERTISERS, ['requestor-cid' => $cid, 'advertiser-ids' => 'joined', 'records-per-page' => 1]))->body());

        if ($xml === null) {
            throw new NetworkException('CJ sent an answer AffilStack couldn\'t read. Try again in a few minutes.');
        }

        if (isset($xml->{'error-message'})) {
            throw NetworkException::rejected('CJ said: '.$this->limit((string) $xml->{'error-message'}, 200));
        }

        return [
            'account_id' => $cid,
            'account_name' => 'CJ publisher '.$cid,
            'credentials' => ['access_token' => $token, 'publisher_cid' => $cid, 'website_id' => $pid],
        ];
    }

    public function programs(NetworkConnection $connection): array
    {
        $programs = [];
        $limit = (int) config('networks.max_available_programs', 1000);

        foreach (['joined' => 'joined', 'notjoined' => 'available'] as $relationship => $status) {
            $page = 1;
            $added = 0;

            do {
                $xml = $this->xml($this->send(fn () => $this->client($this->token($connection))->accept('application/xml')
                    ->get(self::ADVERTISERS, [
                        'requestor-cid' => $connection->credential('publisher_cid'),
                        'advertiser-ids' => $relationship,
                        'records-per-page' => 100,
                        'page-number' => $page,
                    ]))->body());

                $advertisers = $xml?->advertisers;
                $returned = 0;

                foreach ($advertisers?->advertiser ?? [] as $advertiser) {
                    $returned++;
                    $id = trim((string) $advertiser->{'advertiser-id'});

                    if ($id === '' || isset($programs[$id]) || strcasecmp(trim((string) $advertiser->{'account-status'}), 'Active') !== 0) {
                        continue;
                    }

                    $programs[$id] = [
                        'external_id' => $id,
                        'name' => $this->limit((string) $advertiser->{'advertiser-name'}, 190) ?? 'CJ advertiser '.$id,
                        'status' => $status,
                        'homepage_url' => $this->url((string) $advertiser->{'program-url'}),
                        'category' => $this->limit(trim(((string) $advertiser->{'primary-category'}->parent).' / '.((string) $advertiser->{'primary-category'}->child), ' /'), 120),
                        'commission' => $this->commission($advertiser),
                        'deep_linking' => true,
                        'meta' => ['seven_day_epc' => trim((string) $advertiser->{'seven-day-epc'}) ?: null],
                    ];
                    $added++;
                }

                $total = (int) ($advertisers['total-matched'] ?? 0);
                $page++;
            } while ($returned > 0 && ($page - 1) * 100 < $total && ($status === 'joined' ? $page <= 20 : $added < $limit && $page <= 10));
        }

        return array_values($programs);
    }

    protected function commission(SimpleXMLElement $advertiser): ?string
    {
        foreach ($advertiser->actions->action ?? [] as $action) {
            $default = trim((string) ($action->commission->default ?? ''));

            if ($default !== '') {
                return $this->limit($default.' per '.strtolower(trim((string) $action->type) ?: 'sale'), 190);
            }
        }

        return null;
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $orders = [];

        foreach ($this->windows($from, $to, 31) as [$start, $end]) {
            $since = null;

            do {
                $query = sprintf(
                    '{ publisherCommissions(forPublishers: ["%s"], sincePostingDate: "%s", beforePostingDate: "%s"%s) { count payloadComplete maxCommissionId records { commissionId orderId original actionStatus validationStatus advertiserId advertiserName eventDate postingDate pubCommissionAmountUsd shopperId } } }',
                    addslashes((string) $connection->credential('publisher_cid')),
                    $start->utc()->format('Y-m-d\TH:i:s\Z'),
                    $end->utc()->format('Y-m-d\TH:i:s\Z'),
                    $since ? ', sinceCommissionId: "'.addslashes($since).'"' : '',
                );

                $data = $this->send(fn () => $this->client($this->token($connection))
                    ->withBody($query, 'application/x-www-form-urlencoded')
                    ->post(self::COMMISSIONS))->json();

                if (! empty($data['errors'])) {
                    $message = (string) data_get($data, 'errors.0.message', '');
                    throw str_contains(strtolower($message), 'auth')
                        ? NetworkException::rejected('CJ didn\'t accept this access token. Create a new one at developers.cj.com and connect again.')
                        : new NetworkException('CJ returned an error: '.$this->limit($message, 200));
                }

                $result = (array) data_get($data, 'data.publisherCommissions', []);

                foreach ((array) ($result['records'] ?? []) as $record) {
                    if (! is_array($record)) {
                        continue;
                    }

                    $orderKey = ($record['advertiserId'] ?? '').':'.(($record['orderId'] ?? '') !== '' ? $record['orderId'] : $record['commissionId'] ?? '');
                    $order = $orders[$orderKey] ?? [
                        'cents' => 0,
                        'statuses' => [],
                        'declined' => false,
                        'record' => $record,
                    ];

                    $order['cents'] += $this->cents($record['pubCommissionAmountUsd'] ?? 0);
                    $order['statuses'][] = strtolower((string) ($record['actionStatus'] ?? 'new'));
                    $order['declined'] = $order['declined'] || strtoupper((string) ($record['validationStatus'] ?? '')) === 'DECLINED';

                    if ($record['original'] ?? false) {
                        $order['record'] = $record;
                    }

                    $orders[$orderKey] = $order;
                }

                $since = ($result['payloadComplete'] ?? true) ? null : (string) ($result['maxCommissionId'] ?? '');
            } while ($since);
        }

        $earnings = [];

        foreach ($orders as $key => $order) {
            $record = $order['record'];
            $statuses = array_unique($order['statuses']);

            $earnings[] = [
                'external_ref' => 'order:'.$key,
                'amount_cents' => max(0, $order['cents']),
                'currency' => 'USD',
                'status' => match (true) {
                    $order['declined'] || $order['cents'] <= 0 => 'declined',
                    $statuses === ['closed'] => 'paid',
                    in_array('locked', $statuses, true) || in_array('closed', $statuses, true) => 'approved',
                    default => 'pending',
                },
                'converted_at' => $this->date($record['eventDate'] ?? $record['postingDate'] ?? null),
                'sub_id' => $this->limit($record['shopperId'] ?? null, 120),
                'program_external_id' => isset($record['advertiserId']) ? (string) $record['advertiserId'] : null,
                'description' => $this->limit($record['advertiserName'] ?? null, 190),
            ];
        }

        return $earnings;
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        $xml = $this->xml($this->send(fn () => $this->client($this->token($connection))->accept('application/xml')
            ->get(self::LINKS, array_filter([
                'website-id' => $connection->credential('website_id'),
                'advertiser-ids' => $program->external_id,
                'link-type' => 'Text Link',
                'allow-deep-linking' => $destination !== null ? 'true' : null,
                'records-per-page' => 1,
            ])))->body());

        $click = $this->url(trim((string) ($xml?->links?->link[0]?->clickUrl ?? '')));

        if ($click === null) {
            return $program->tracking_url;
        }

        return $destination !== null ? $this->withQuery($click, ['url' => $destination], true) : $click;
    }

    public function withSubId(string $url, string $subId): string
    {
        return $this->withQuery($url, ['sid' => $this->safeSubId($subId, 64)]);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['anrdoezrs.net', 'jdoqocy.com', 'tkqlhce.com', 'dpbolvw.net', 'kqzyfj.com']);
    }

    protected function token(NetworkConnection $connection): string
    {
        return (string) $connection->credential('access_token');
    }

    protected function xml(string $body): ?SimpleXMLElement
    {
        if (trim($body) === '') {
            return null;
        }

        $previous = libxml_use_internal_errors(true);

        try {
            $xml = simplexml_load_string($body, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);
        } finally {
            libxml_use_internal_errors($previous);
        }

        return $xml === false ? null : $xml;
    }
}
