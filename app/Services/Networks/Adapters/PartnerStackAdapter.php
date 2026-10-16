<?php

namespace App\Services\Networks\Adapters;

use App\Models\NetworkConnection;
use App\Models\NetworkProgram;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\PendingRequest;

/**
 * PartnerStack Partner API v2 (https://api.partnerstack.com/api/v2/…):
 * a bearer API key, cursor pagination ("starting_after"), dates in epoch
 * milliseconds and amounts in cents. Partnerships are the joined programs,
 * the marketplace lists ones to apply to, rewards are the commissions.
 * The API can't make custom links, so the partnership's own link is used.
 * Sub-ID: ?sid= (echoed back on rewards as customer.sub_ids).
 */
class PartnerStackAdapter extends BaseAdapter
{
    public const BASE = 'https://api.partnerstack.com/api/v2/';

    protected function label(): string
    {
        return 'PartnerStack';
    }

    protected function client(string $key): PendingRequest
    {
        return $this->http()->withToken($key);
    }

    public function verify(array $credentials): array
    {
        $key = trim((string) ($credentials['api_key'] ?? ''));

        if (strlen($key) < 10) {
            throw NetworkException::rejected('Paste the API key from Settings → API in your PartnerStack partner dashboard.');
        }

        $data = $this->send(fn () => $this->client($key)->get(self::BASE.'partnerships', ['limit' => 1]))->json();
        $first = data_get($data, 'data.items.0');

        return [
            'account_id' => substr(hash('sha256', $key), 0, 12),
            'account_name' => 'PartnerStack partner'.($first ? '' : ' (no programs yet)'),
            'credentials' => ['api_key' => $key],
        ];
    }

    public function programs(NetworkConnection $connection): array
    {
        $programs = [];

        foreach ($this->items($connection, 'partnerships', ['include_offers' => 'true']) as $partnership) {
            $status = (string) ($partnership['status'] ?? '');

            if (! in_array($status, ['active', 'application-pending', 'program-waitlisted'], true)) {
                continue;
            }

            $companyKey = (string) data_get($partnership, 'company.key', $partnership['key'] ?? '');

            $programs[$companyKey] = [
                'external_id' => $companyKey,
                'name' => $this->limit(data_get($partnership, 'company.name'), 190) ?? 'PartnerStack program',
                'status' => $status === 'active' ? 'joined' : 'pending',
                'homepage_url' => $this->url(data_get($partnership, 'link.destination')),
                'tracking_url' => $this->url(data_get($partnership, 'link.url')),
                'currency' => $this->offerCurrency($partnership['offers'] ?? []),
                'commission' => $this->offerSummary($partnership['offers'] ?? []),
                'deep_linking' => false,
                'meta' => ['partnership_key' => $partnership['key'] ?? null, 'sub_ids' => (bool) ($partnership['has_sub_id'] ?? false)],
            ];
        }

        $limit = (int) config('networks.max_available_programs', 1000);
        $count = 0;

        foreach ($this->items($connection, 'marketplace/programs', [], $limit) as $program) {
            $key = (string) ($program['key'] ?? '');

            if ($key === '' || isset($programs[$key])) {
                continue;
            }

            $programs[$key] = [
                'external_id' => $key,
                'name' => $this->limit($program['name'] ?? null, 190) ?? 'PartnerStack program',
                'status' => 'available',
                'homepage_url' => $this->url($program['website'] ?? null),
                'category' => $this->limit(implode(', ', array_filter((array) ($program['category'] ?? []), 'is_string')), 120),
                'currency' => $this->offerCurrency($program['offers'] ?? []),
                'commission' => $this->offerSummary($program['offers'] ?? []),
                'description' => $this->limit($program['description'] ?? null, 2000),
                'deep_linking' => false,
                'meta' => ['sub_ids' => (bool) ($program['has_sub_id'] ?? false)],
            ];

            if (++$count >= $limit) {
                break;
            }
        }

        return array_values($programs);
    }

    public function earnings(NetworkConnection $connection, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $earnings = [];
        $params = ['min_created' => $from->getTimestampMs(), 'max_created' => $to->getTimestampMs()];

        foreach ($this->items($connection, 'rewards', $params) as $reward) {
            $key = (string) ($reward['key'] ?? '');

            if ($key === '') {
                continue;
            }

            $status = (string) ($reward['reward_status'] ?? 'pending');
            $payment = (string) ($reward['payment_status'] ?? '');

            $earnings[] = [
                'external_ref' => $key,
                'amount_cents' => abs((int) ($reward['amount'] ?? 0)),
                'currency' => $this->currency($reward['currency'] ?? 'USD'),
                'status' => match (true) {
                    $status === 'declined' => 'declined',
                    $status === 'paid' || in_array($payment, ['withdrawn', 'paid_externally'], true) => 'paid',
                    $status === 'approved' => 'approved',
                    default => 'pending',
                },
                'converted_at' => $this->date($reward['created_at'] ?? null),
                'sub_id' => $this->limit(data_get($reward, 'customer.sub_ids.0'), 120),
                'program_external_id' => data_get($reward, 'company.key'),
                'description' => $this->limit(data_get($reward, 'company.name') ?? ($reward['description'] ?? null), 190),
            ];
        }

        return $earnings;
    }

    public function trackingLink(NetworkConnection $connection, NetworkProgram $program, ?string $destination = null): ?string
    {
        return $program->tracking_url;
    }

    public function withSubId(string $url, string $subId): string
    {
        return $this->withQuery($url, ['sid' => $this->safeSubId($subId, 100)]);
    }

    public function recognises(string $url): bool
    {
        return $this->hostIs($url, ['grsm.io']);
    }

    /**
     * @param  array<int, mixed>  $offers
     */
    protected function offerSummary(mixed $offers): ?string
    {
        $offer = collect((array) $offers)->first(fn ($item) => is_array($item));

        if (! $offer) {
            return null;
        }

        return $this->limit((string) ($offer['description'] ?? '') ?: trim(($offer['base_rate'] ?? '').' '.($offer['type'] ?? $offer['type_'] ?? '')), 190);
    }

    protected function offerCurrency(mixed $offers): ?string
    {
        $offer = collect((array) $offers)->first(fn ($item) => is_array($item) && ! empty($item['currency']));

        return $offer ? $this->currency($offer['currency']) : null;
    }

    /**
     * @param  array<string, mixed>  $params
     * @return \Generator<int, array<string, mixed>>
     */
    protected function items(NetworkConnection $connection, string $path, array $params = [], int $max = 5000): \Generator
    {
        $client = $this->client((string) $connection->credential('api_key'));
        $after = null;
        $seen = 0;

        do {
            $query = $params + ['limit' => 250] + ($after ? ['starting_after' => $after] : []);
            $data = (array) $this->send(fn () => $client->get(self::BASE.$path, $query))->json('data', []);
            $items = array_values(array_filter((array) ($data['items'] ?? []), 'is_array'));

            foreach ($items as $item) {
                yield $item;
                $seen++;
            }

            $after = $items ? ($items[count($items) - 1]['key'] ?? null) : null;
        } while (($data['has_more'] ?? false) && $after && $seen < $max);
    }
}
