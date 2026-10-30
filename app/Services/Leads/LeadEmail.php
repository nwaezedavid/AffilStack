<?php

namespace App\Services\Leads;

/**
 * Small, pure helpers for judging an email address found for a lead.
 */
class LeadEmail
{
    public static function domainOf(string $email): string
    {
        return strtolower((string) substr(strrchr($email, '@') ?: '', 1));
    }

    public static function domainOfUrl(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $host = parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://'.$url, PHP_URL_HOST);

        return $host ? preg_replace('/^www\./', '', strtolower($host)) : null;
    }

    public static function isFreeMail(string $email): bool
    {
        return in_array(self::domainOf($email), (array) config('leads.free_mail_domains', []), true);
    }

    /**
     * A syntactically valid address that isn't an image name, a site
     * builder's placeholder or a no-reply box.
     */
    public static function isUsable(string $email): bool
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            return false;
        }

        foreach ((array) config('leads.ignored_email_patterns', []) as $pattern) {
            if (preg_match($pattern, $email)) {
                return false;
            }
        }

        return true;
    }
}
