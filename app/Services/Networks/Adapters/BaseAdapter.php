<?php

namespace App\Services\Networks\Adapters;

use App\Services\Networks\NetworkAdapter;
use App\Services\Networks\NetworkException;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shared plumbing for the network adapters: an HTTP client with sensible
 * timeouts, turning failures into messages a customer can act on, money
 * and date parsing, and URL helpers for sub-IDs.
 */
abstract class BaseAdapter implements NetworkAdapter
{
    abstract protected function label(): string;

    protected function http(): PendingRequest
    {
        return Http::timeout(30)
            ->connectTimeout(10)
            ->acceptJson()
            ->withHeaders(['User-Agent' => 'AffilStack/1.0 (+affiliate network sync)']);
    }

    /**
     * @param  callable(): Response  $call
     *
     * @throws NetworkException
     */
    protected function send(callable $call): Response
    {
        try {
            $response = $call();
        } catch (ConnectionException $e) {
            throw new NetworkException($this->label().' didn\'t respond. Try again in a few minutes.');
        }

        if ($response->status() === 401 || ($response->status() === 403 && $this->forbiddenMeansBadKeys())) {
            throw NetworkException::rejected($this->label().' didn\'t accept these keys. Check they\'re copied in full and still active, then connect again.');
        }

        if ($response->status() === 429) {
            throw new NetworkException($this->label().' is limiting how often AffilStack can ask for data. The next sync will try again.');
        }

        if ($response->failed()) {
            throw new NetworkException($this->label().' returned an error (HTTP '.$response->status().'). Try again later.');
        }

        return $response;
    }

    protected function forbiddenMeansBadKeys(): bool
    {
        return true;
    }

    protected function cents(mixed $amount): int
    {
        if (is_array($amount)) {
            $amount = $amount['amount'] ?? 0;
        }

        $clean = preg_replace('/[^0-9.\-]/', '', (string) $amount);

        return is_numeric($clean) ? (int) round(((float) $clean) * 100) : 0;
    }

    protected function currency(mixed $code, string $default = 'USD'): string
    {
        $code = strtoupper(trim((string) $code));

        return preg_match('/^[A-Z]{3}$/', $code) ? $code : $default;
    }

    protected function date(mixed $value): string
    {
        if (is_numeric($value) && (int) $value > 100000000000) {
            return CarbonImmutable::createFromTimestampMs((int) $value)->toDateString();
        }

        try {
            return CarbonImmutable::parse((string) $value)->toDateString();
        } catch (\Throwable) {
            return now()->toDateString();
        }
    }

    protected function limit(?string $value, int $length): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : Str::limit($value, $length, '');
    }

    protected function url(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value !== '' && ! preg_match('#^https?://#i', $value) && preg_match('#^[a-z0-9.-]+\.[a-z]{2,}#i', $value)) {
            $value = 'https://'.$value;
        }

        return filter_var($value, FILTER_VALIDATE_URL) && preg_match('#^https?://#i', $value) ? Str::limit($value, 1000, '') : null;
    }

    /**
     * Splits a range into windows a network accepts.
     *
     * @return array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>
     */
    protected function windows(CarbonImmutable $from, CarbonImmutable $to, int $days): array
    {
        $windows = [];
        $start = $from;

        while ($start->lessThan($to)) {
            $end = $start->addDays($days)->min($to);
            $windows[] = [$start, $end];
            $start = $end;
        }

        return $windows;
    }

    /**
     * Sets query parameters, keeping any the link already has unless told
     * to overwrite.
     *
     * @param  array<string, string>  $params
     */
    protected function withQuery(string $url, array $params, bool $overwrite = false): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return $url;
        }

        parse_str($parts['query'] ?? '', $query);

        foreach ($params as $key => $value) {
            if ($overwrite || ! array_key_exists($key, $query)) {
                $query[$key] = $value;
            }
        }

        return $parts['scheme'].'://'.$parts['host']
            .(isset($parts['port']) ? ':'.$parts['port'] : '')
            .($parts['path'] ?? '')
            .($query ? '?'.http_build_query($query) : '')
            .(isset($parts['fragment']) ? '#'.$parts['fragment'] : '');
    }

    protected function host(string $url): string
    {
        return strtolower((string) parse_url($url, PHP_URL_HOST));
    }

    /**
     * @param  array<int, string>  $domains
     */
    protected function hostIs(string $url, array $domains): bool
    {
        $host = $this->host($url);

        foreach ($domains as $domain) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A sub-ID with only the characters every network accepts.
     */
    protected function safeSubId(string $subId, int $length = 50): string
    {
        return substr(preg_replace('/[^a-z0-9_]/', '', strtolower($subId)) ?? '', 0, $length);
    }
}
