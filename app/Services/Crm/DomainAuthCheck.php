<?php

namespace App\Services\Crm;

/**
 * SPF and DMARC records for a sending domain (cached for an hour) — the
 * Email Sending page's deliverability indicators. Free mailbox domains
 * (gmail.com etc.) are the provider's responsibility, so they're reported
 * as managed without a lookup. DKIM needs the provider's selector, so it
 * is explained rather than checked.
 */
class DomainAuthCheck
{
    protected const MANAGED = ['gmail.com', 'googlemail.com', 'outlook.com', 'hotmail.com', 'live.com', 'yahoo.com', 'icloud.com', 'zoho.com', 'zohomail.com'];

    /**
     * @return array{domain: string, managed: bool, spf: bool|null, dmarc: bool|null}|null
     */
    public function check(string $email): ?array
    {
        $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));

        if ($domain === '' || ! str_contains($domain, '.')) {
            return null;
        }

        if (in_array($domain, self::MANAGED, true)) {
            return ['domain' => $domain, 'managed' => true, 'spf' => true, 'dmarc' => true];
        }

        return cache()->remember('crm-dns-health:'.$domain, 3600, function () use ($domain) {
            try {
                return [
                    'domain' => $domain,
                    'managed' => false,
                    'spf' => $this->hasTxt($domain, 'v=spf1'),
                    'dmarc' => $this->hasTxt('_dmarc.'.$domain, 'v=dmarc1'),
                ];
            } catch (\Throwable) {
                return ['domain' => $domain, 'managed' => false, 'spf' => null, 'dmarc' => null];
            }
        });
    }

    protected function hasTxt(string $host, string $prefix): bool
    {
        foreach (@dns_get_record($host, DNS_TXT) ?: [] as $record) {
            if (str_starts_with(strtolower((string) ($record['txt'] ?? '')), $prefix)) {
                return true;
            }
        }

        return false;
    }
}
