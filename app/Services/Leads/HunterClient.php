<?php

namespace App\Services\Leads;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Hunter.io domain search + email verification. The key travels in the
 * X-API-KEY header, never the query string, so it stays out of logs.
 */
class HunterClient
{
    public function __construct(protected string $apiKey) {}

    /**
     * Business emails Hunter knows for a company domain, best first.
     *
     * @return array<int, array{email: string, name: string, title: ?string, confidence: int, linkedin_url: ?string}>
     */
    public function domainSearch(string $domain, int $limit = 5): array
    {
        $response = $this->client()->get($this->url('/domain-search'), [
            'domain' => $domain,
            'limit' => max(1, min(10, $limit)),
            'type' => 'personal',
        ]);
        $this->ensureOk($response, 'Hunter domain search');

        return collect($response->json('data.emails') ?? [])
            ->filter(fn (mixed $row): bool => is_array($row) && filled($row['value'] ?? null))
            ->map(fn (array $row): array => [
                'email' => strtolower((string) $row['value']),
                'name' => trim(($row['first_name'] ?? '').' '.($row['last_name'] ?? '')),
                'title' => $row['position'] ?? null,
                'confidence' => (int) ($row['confidence'] ?? 0),
                'linkedin_url' => $row['linkedin'] ?? null,
            ])
            ->sortByDesc('confidence')
            ->values()
            ->all();
    }

    /**
     * @return string one of valid, risky, invalid
     */
    public function verify(string $email): string
    {
        $response = $this->client()->get($this->url('/email-verifier'), ['email' => $email]);
        $this->ensureOk($response, 'Hunter verification');

        return match ((string) $response->json('data.status')) {
            'valid' => 'valid',
            'invalid', 'disposable' => 'invalid',
            default => 'risky', // accept_all, webmail, unknown
        };
    }

    public function verifyKey(): string
    {
        $response = $this->client()->get($this->url('/account'));
        $this->ensureOk($response, 'Hunter');

        $left = $response->json('data.requests.searches.available');

        return 'Hunter accepted the API key.'.($left !== null ? " Searches available this month: {$left}." : '');
    }

    protected function url(string $path): string
    {
        return rtrim((string) config('leads.hunter.base_url'), '/').$path;
    }

    protected function client(): PendingRequest
    {
        return Http::timeout((int) config('leads.hunter.timeout', 20))
            ->acceptJson()
            ->withHeaders(['X-API-KEY' => $this->apiKey]);
    }

    protected function ensureOk(Response $response, string $what): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = (string) ($response->json('errors.0.details') ?? $response->reason());

        throw new LeadSourceException(match ($response->status()) {
            401 => "{$what}: Hunter did not accept the API key.",
            429 => "{$what}: Hunter's rate limit or monthly quota was reached.",
            default => "{$what} failed: HTTP {$response->status()} {$reason}",
        });
    }
}
