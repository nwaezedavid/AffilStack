<?php

namespace App\Support\Security;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Is the PHP that runs AffilStack safe? PHP2Shell (CVE-2026-109618 and
 * CVE-2026-109619) attacks PHP itself before any application code runs, so
 * the only real protection is a patched PHP on the server. This class
 * knows which versions are patched, records which PHP the website actually
 * serves with (the cron's command-line PHP can differ from it on Hostinger),
 * asks php.net daily whether a newer security release exists, and checks
 * the php.ini settings that make PHP2Shell easier to reach.
 */
class PhpRuntime
{
    protected const RELEASE_CACHE_PREFIX = 'security:php-latest:';

    /**
     * The PHP the website served with most recently, as recorded by
     * RecordPhpRuntime.
     *
     * @return array{version: string, sapi: string, build_date: string|null, ini: array<string, string|false>, recorded_at: int}|null
     */
    public static function webRuntime(): ?array
    {
        $path = static::recordPath();

        if (! is_file($path)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($path), true);

        return is_array($data) && isset($data['version'], $data['sapi']) ? $data + ['build_date' => null, 'ini' => [], 'recorded_at' => 0] : null;
    }

    /**
     * Writes this request's PHP version (and the risky ini values) for the
     * daily check — only when it changed or the record is old, so a normal
     * request costs one small file read.
     */
    public static function recordCurrent(): void
    {
        $path = static::recordPath();
        $current = static::webRuntime();
        $fresh = $current !== null
            && $current['version'] === PHP_VERSION
            && $current['sapi'] === PHP_SAPI
            && $current['recorded_at'] > time() - (int) config('security.php_runtime_record_minutes', 360) * 60;

        if ($fresh) {
            return;
        }

        $ini = [];

        foreach ([...array_keys((array) config('security.php_risky_ini', [])), 'post_max_size', 'disable_functions'] as $key) {
            $ini[$key] = ini_get($key);
        }

        @file_put_contents($path, json_encode([
            'version' => PHP_VERSION,
            'sapi' => PHP_SAPI,
            'build_date' => static::buildDate(),
            'ini' => $ini,
            'recorded_at' => time(),
        ]), LOCK_EX);
    }

    /**
     * Everything wrong with a PHP version, worst first.
     *
     * Managed hosts (Hostinger's CloudLinux PHP among them) often backport a
     * security fix into the version they already run without changing its
     * number, so a PHP built on or after a fix's release date is treated as
     * carrying it — reported as a low note to confirm with the host rather
     * than as critical.
     *
     * @return array<int, array{severity: string, title: string, detail: string}>
     */
    public static function problemsFor(string $version, bool $checkPhpNet = true, ?string $buildDate = null): array
    {
        $branch = static::branch($version);
        $problems = [];

        if (! in_array($branch, (array) config('security.php_supported_branches', []), true)) {
            $problems[] = [
                'severity' => 'critical',
                'title' => "PHP {$version} is not a supported version for AffilStack",
                'detail' => 'AffilStack runs on PHP '.implode(' or ', (array) config('security.php_supported_branches', [])).'. Switch the website to one of them in hPanel → Advanced → PHP Configuration.',
            ];

            return $problems;
        }

        $minimum = ((array) config('security.php_minimum_patches', []))[$branch] ?? null;
        $fixedOn = ((array) config('security.php_fix_released', []))[$branch] ?? null;
        $belowFix = is_string($minimum) && version_compare($version, $minimum, '<');
        $backported = $belowFix && static::builtOnOrAfter($buildDate, $fixedOn);

        if ($belowFix && ! $backported) {
            $problems[] = [
                'severity' => 'critical',
                'title' => "PHP {$version} is vulnerable to PHP2Shell — {$minimum} or newer is needed",
                'detail' => "PHP2Shell (CVE-2026-109618 and CVE-2026-109619) lets an attacker run code on the server without logging in, before AffilStack itself runs. PHP {$minimum} fixes it. Ask the host to update PHP {$branch} to {$minimum} or newer (or confirm they have backported the fix), then restart PHP.",
            ];
        } elseif ($backported) {
            $problems[] = [
                'severity' => 'low',
                'title' => "PHP {$version} was built on {$buildDate}, after the PHP2Shell fix — the host's backport is assumed",
                'detail' => "The version number is older than {$minimum}, but this build is dated after the fix was released ({$fixedOn}). Hosts such as Hostinger backport the fix without changing the number. If in doubt, ask the host to confirm their PHP {$branch} build includes the PHP2Shell fix (CVE-2026-109618 and CVE-2026-109619).",
            ];
        }

        if ($checkPhpNet && ($latest = static::latestRelease($branch)) && version_compare($version, $latest['version'], '<')
            && ! static::builtOnOrAfter($buildDate, $latest['date'] ?? null)) {
            $security = in_array('security', $latest['tags'], true);

            // Already covered by the PHP2Shell message above.
            if (! ($belowFix && ! $backported && $minimum === $latest['version'])) {
                $problems[] = [
                    'severity' => $security ? 'high' : 'low',
                    'title' => $security
                        ? "PHP {$latest['version']} is a security release and this server runs {$version}"
                        : "PHP {$latest['version']} is available and this server runs {$version}",
                    'detail' => $security
                        ? "php.net released PHP {$latest['version']} with security fixes. Ask the host to update PHP {$branch} (or confirm they have backported the fixes), then restart PHP."
                        : "A newer PHP {$branch} release is out. Updating soon keeps the server current.",
                ];
            }
        }

        return $problems;
    }

    /**
     * The date this PHP binary was built ("Y-m-d"), from phpinfo(), or null
     * when it can't be read.
     */
    public static function buildDate(): ?string
    {
        static $date = false;

        if ($date !== false) {
            return $date;
        }

        try {
            ob_start();
            phpinfo(INFO_GENERAL);
            $info = strip_tags((string) ob_get_clean());
        } catch (Throwable) {
            return $date = null;
        }

        if (preg_match('/Build Date\s*(?:=>)?\s*([A-Z][a-z]{2}\s+\d{1,2}\s+\d{4})/', $info, $match) !== 1) {
            return $date = null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!M j Y', (string) preg_replace('/\s+/', ' ', $match[1]));

        return $date = $parsed ? $parsed->format('Y-m-d') : null;
    }

    protected static function builtOnOrAfter(?string $buildDate, ?string $releaseDate): bool
    {
        if ($buildDate === null || $releaseDate === null) {
            return false;
        }

        $build = strtotime($buildDate);
        $release = strtotime($releaseDate);

        return $build !== false && $release !== false && $build >= $release;
    }

    /**
     * php.ini settings on the website that should be off.
     *
     * @param  array<string, string|false>  $ini
     * @return array<int, array{severity: string, title: string, detail: string}>
     */
    public static function riskyIniProblems(array $ini): array
    {
        $problems = [];

        foreach ((array) config('security.php_risky_ini', []) as $key => $safe) {
            if (! array_key_exists($key, $ini) || $ini[$key] === false) {
                continue;
            }

            if (static::normalizeIni((string) $ini[$key]) !== static::normalizeIni((string) $safe)) {
                $problems[] = [
                    'severity' => 'medium',
                    'title' => "PHP setting {$key} is on",
                    'detail' => "AffilStack doesn't use it, and PHP2Shell can be reached through it. public/.user.ini turns it off; if the host ignores that file, set {$key} = {$safe} in hPanel → Advanced → PHP Configuration or ask the host to.",
                ];
            }
        }

        if (isset($ini['disable_functions']) && is_string($ini['disable_functions'])) {
            $disabled = array_map('trim', explode(',', strtolower($ini['disable_functions'])));
            $open = array_values(array_diff((array) config('security.web_disabled_functions', []), $disabled));

            if ($open !== []) {
                $problems[] = [
                    'severity' => 'medium',
                    'title' => 'The website can start other programs ('.implode(', ', $open).' allowed)',
                    'detail' => 'AffilStack\'s website never needs these (the cron job turns them on for itself), but a web shell would use them to take over the server. Add '.implode(', ', $open).' to disable_functions in hPanel → Advanced → PHP Configuration → PHP options.',
                ];
            }
        }

        $maxPost = (int) config('security.php_max_post_megabytes', 64);

        if (isset($ini['post_max_size']) && is_string($ini['post_max_size']) && static::megabytes($ini['post_max_size']) > $maxPost) {
            $problems[] = [
                'severity' => 'medium',
                'title' => "PHP accepts requests up to {$ini['post_max_size']} (post_max_size)",
                'detail' => 'AffilStack never needs more than about 25 MB, and very large requests are how PHP2Shell exhausts memory. public/.user.ini lowers it to 40M; if the host ignores that file, choose the smallest post_max_size and upload_max_filesize in hPanel → Advanced → PHP Configuration.',
            ];
        }

        return $problems;
    }

    /**
     * "2048M", "1G", "40M", "0" (unlimited) as megabytes.
     */
    public static function megabytes(string $value): float
    {
        $value = trim($value);
        $number = (float) $value;

        if ($number <= 0) {
            return INF;
        }

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024,
            'm' => $number,
            'k' => $number / 1024,
            default => $number / 1048576,
        };
    }

    /**
     * The newest release of a branch on php.net, cached for 12 hours.
     * Null when php.net can't be reached or the check is off.
     *
     * @return array{version: string, tags: array<int, string>, date: string|null}|null
     */
    public static function latestRelease(string $branch): ?array
    {
        if (! config('security.php_release_check', true)) {
            return null;
        }

        try {
            return Cache::remember(static::RELEASE_CACHE_PREFIX.$branch, now()->addHours(12), function () use ($branch) {
                $response = Http::timeout(10)->acceptJson()
                    ->get((string) config('security.php_release_feed'), ['json' => '', 'version' => $branch]);

                $version = $response->json('version');

                if (! $response->successful() || ! is_string($version) || ! preg_match('/^\d+\.\d+\.\d+$/', $version) || static::branch($version) !== $branch) {
                    throw new \RuntimeException('Unexpected answer from php.net.');
                }

                $date = $response->json('date');

                return [
                    'version' => $version,
                    'tags' => array_values(array_filter((array) $response->json('tags', []), 'is_string')),
                    'date' => is_string($date) && strtotime($date) !== false ? date('Y-m-d', (int) strtotime($date)) : null,
                ];
            });
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * True when the website's PHP is known to be below a critical fix.
     */
    public static function webIsCriticallyVulnerable(): bool
    {
        $web = static::webRuntime();

        return $web !== null && collect(static::problemsFor($web['version'], false, $web['build_date']))->contains('severity', 'critical');
    }

    public static function branch(string $version): string
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    protected static function normalizeIni(string $value): string
    {
        return in_array(strtolower(trim($value)), ['', '0', 'off', 'false', 'no', 'none'], true) ? '0' : '1';
    }

    protected static function recordPath(): string
    {
        return storage_path('framework/php-runtime.json');
    }
}
