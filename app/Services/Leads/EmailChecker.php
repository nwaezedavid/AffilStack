<?php

namespace App\Services\Leads;

use Throwable;

/**
 * Checks a lead's email can receive mail: Hunter's verifier when it's
 * configured, otherwise a DNS check that the domain accepts mail (MX).
 */
class EmailChecker
{
    /** @var (callable(string): bool)|null */
    protected static $mxResolver = null;

    public function __construct(protected ?HunterClient $hunter = null) {}

    /**
     * Swap the MX lookup (tests) — null restores real DNS.
     *
     * @param  (callable(string): bool)|null  $resolver
     */
    public static function resolveMxUsing(?callable $resolver): void
    {
        static::$mxResolver = $resolver;
    }

    /**
     * @return string verified (mailbox confirmed), deliverable (domain takes mail), risky, or invalid
     */
    public function check(string $email): string
    {
        if (! LeadEmail::isUsable($email)) {
            return 'invalid';
        }

        if (! $this->domainAcceptsMail(LeadEmail::domainOf($email))) {
            return 'invalid';
        }

        if ($this->hunter) {
            try {
                return match ($this->hunter->verify($email)) {
                    'valid' => 'verified',
                    'invalid' => 'invalid',
                    default => 'risky',
                };
            } catch (Throwable) {
                // A verifier outage or exhausted quota falls back to the DNS result.
            }
        }

        return 'deliverable';
    }

    public static function isGood(string $status): bool
    {
        return in_array($status, ['verified', 'deliverable'], true);
    }

    protected function domainAcceptsMail(string $domain): bool
    {
        if ($domain === '') {
            return false;
        }

        if (static::$mxResolver !== null) {
            return (bool) (static::$mxResolver)($domain);
        }

        return checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A');
    }
}
