<?php

namespace App\Services\Leads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Apollo.io people search + enrichment, called over Laravel's HTTP client
 * (no SDK, per the no-new-dependencies rule).
 *
 * Search is free on Apollo's side but returns no emails; each enrichment
 * (people/match) costs the platform an Apollo credit, so LeadFinder only
 * enriches the people it is actually about to save.
 */
class ApolloClient
{
    public function __construct(protected string $apiKey) {}

    /**
     * @param  array<int, string>  $titles
     * @param  array<int, string>  $locations
     * @param  array<int, string>  $companyKeywords
     * @return array<int, array{id: string, name: string, title: ?string, company: ?string}>
     */
    public function searchPeople(array $titles, array $locations, array $companyKeywords, int $perPage = 25, int $page = 1): array
    {
        $query = $this->query([
            'person_titles' => array_slice($titles, 0, 10),
            'include_similar_titles' => 'true',
            'organization_locations' => array_slice($locations, 0, 5),
            'q_organization_keyword_tags' => array_slice($companyKeywords, 0, 10),
            'page' => max(1, $page),
            'per_page' => max(1, min(100, $perPage)),
        ]);

        $response = $this->client()->withBody('{}', 'application/json')->post($this->url('/mixed_people/api_search').'?'.$query);
        $this->ensureOk($response, 'Apollo search');

        return collect($response->json('people') ?? [])
            ->filter(fn (mixed $person): bool => is_array($person) && filled($person['id'] ?? null))
            ->map(fn (array $person): array => [
                'id' => (string) $person['id'],
                'name' => trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) ?: (string) ($person['name'] ?? ''),
                'title' => $person['title'] ?? null,
                'company' => data_get($person, 'organization.name') ?? ($person['organization_name'] ?? null),
            ])
            ->values()
            ->all();
    }

    /**
     * Business email and profile for one person found by searchPeople().
     * Personal emails are never requested.
     *
     * @return array{name: string, title: ?string, email: ?string, linkedin_url: ?string, company: ?string, website: ?string, location: ?string}|null
     */
    public function enrich(string $personId): ?array
    {
        $response = $this->client()->withBody('{}', 'application/json')->post($this->url('/people/match').'?'.$this->query([
            'id' => $personId,
            'reveal_personal_emails' => 'false',
            'reveal_phone_number' => 'false',
        ]));
        $this->ensureOk($response, 'Apollo enrichment');

        $person = $response->json('person');

        if (! is_array($person)) {
            return null;
        }

        $website = data_get($person, 'organization.website_url')
            ?? (($domain = data_get($person, 'organization.primary_domain')) ? 'https://'.$domain : null);

        return [
            'name' => trim(($person['first_name'] ?? '').' '.($person['last_name'] ?? '')) ?: (string) ($person['name'] ?? ''),
            'title' => $person['title'] ?? null,
            'email' => $person['email'] ?? null,
            'linkedin_url' => $person['linkedin_url'] ?? null,
            'company' => data_get($person, 'organization.name'),
            'website' => $website,
            'location' => implode(', ', array_filter([$person['city'] ?? null, $person['state'] ?? null, $person['country'] ?? null])) ?: null,
        ];
    }

    /**
     * For the admin "Test" button.
     */
    public function verify(): string
    {
        $response = $this->client()->get($this->url('/auth/health'));
        $this->ensureOk($response, 'Apollo');

        if ($response->json('is_logged_in') === false) {
            throw new LeadSourceException('Apollo did not accept this API key.');
        }

        return 'Apollo accepted the API key.';
    }

    /**
     * Apollo reads array filters as repeated "name[]=value" query params.
     *
     * @param  array<string, mixed>  $params
     */
    protected function query(array $params): string
    {
        $pairs = [];

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $item) {
                    $pairs[] = rawurlencode($key).'[]='.rawurlencode((string) $item);
                }
            } elseif ($value !== null && $value !== '') {
                $pairs[] = rawurlencode($key).'='.rawurlencode((string) $value);
            }
        }

        return implode('&', $pairs);
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('leads.apollo.base_url'), '/').$path;
    }

    protected function client(): PendingRequest
    {
        return Http::timeout((int) config('leads.apollo.timeout', 20))
            ->acceptJson()
            ->withHeaders(['x-api-key' => $this->apiKey, 'Cache-Control' => 'no-cache']);
    }

    protected function ensureOk(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = (string) ($response->json('error') ?? $response->json('message') ?? $response->reason());

        throw new LeadSourceException(match ($response->status()) {
            401 => "{$what}: Apollo did not accept the API key.",
            403 => "{$what}: this Apollo API key isn't allowed to use this endpoint — create a master API key in Apollo.",
            422 => "{$what}: Apollo rejected the search ({$reason}).",
            429 => "{$what}: Apollo's rate limit was reached — try again shortly.",
            default => "{$what} failed: HTTP {$response->status()} {$reason}",
        });
    }
}
