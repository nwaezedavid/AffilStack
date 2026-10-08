<?php

namespace App\Support\Analytics;

use App\Models\ConsentRecord;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Agent 04: a visitor's cookie choice, read from the strictly-necessary
 * afs_consent cookie the consent banner writes client-side
 * ("v:<policy version>|a:<0|1>|m:<0|1>|id:<uuid>|t:<unix time>"). The server
 * re-checks it on every beacon, so analytics data is only ever stored when
 * the browser itself says the visitor agreed — whatever the page JS does.
 */
final class ConsentState
{
    public function __construct(
        public readonly ?string $policyVersion,
        public readonly bool $analytics,
        public readonly bool $marketing,
        public readonly ?string $consentId,
    ) {}

    public static function none(): self
    {
        return new self(null, false, false, null);
    }

    public static function fromRequest(Request $request): self
    {
        return self::fromCookieValue($request->cookies->get((string) config('analytics.cookies.consent')));
    }

    public static function fromCookieValue(mixed $value): self
    {
        if (! is_string($value) || $value === '' || strlen($value) > 200) {
            return self::none();
        }

        $parts = [];
        foreach (explode('|', urldecode($value)) as $pair) {
            [$key, $val] = array_pad(explode(':', $pair, 2), 2, null);
            if ($key !== null && $val !== null) {
                $parts[$key] = $val;
            }
        }

        $version = $parts['v'] ?? null;
        $id = $parts['id'] ?? null;

        return new self(
            ConsentSettings::isValidVersion($version) ? $version : null,
            ($parts['a'] ?? '0') === '1',
            ($parts['m'] ?? '0') === '1',
            is_string($id) && Str::isUuid($id) ? strtolower($id) : null,
        );
    }

    public static function fromRecord(?ConsentRecord $record): self
    {
        if (! $record) {
            return self::none();
        }

        return new self($record->policy_version, $record->analytics, $record->marketing, $record->consent_id);
    }

    /**
     * Was this choice made against the policy version currently in force?
     * Bumping the version in the admin makes every older choice stale, so
     * the banner asks again and nothing optional runs meanwhile.
     */
    public function isCurrent(): bool
    {
        return $this->policyVersion !== null && $this->policyVersion === ConsentSettings::policyVersion();
    }

    public function allowsAnalytics(): bool
    {
        return $this->isCurrent() && $this->analytics;
    }

    public function allowsMarketing(): bool
    {
        return $this->isCurrent() && $this->marketing;
    }
}
