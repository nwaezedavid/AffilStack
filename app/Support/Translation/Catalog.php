<?php

namespace App\Support\Translation;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

/**
 * AffilStack's own interface strings: every quoted string in the app's
 * PHP code, config and Blade templates. A value echoed into a page that
 * matches one ("Pending", a menu label, a button that depends on a
 * condition) is part of the interface and gets translated; anything else
 * is customer data and is left alone (see Mark).
 *
 * Built once per deploy (or by translations:catalog) and kept as a PHP
 * file of hashes, so a lookup costs nothing.
 */
class Catalog
{
    /** @var array<string, true>|null */
    protected static ?array $set = null;

    public static function has(string $value): bool
    {
        $normalized = static::normalize($value);

        if ($normalized === '' || mb_strlen($normalized) > 400) {
            return false;
        }

        return isset(static::load()[md5($normalized)]);
    }

    public static function normalize(string $value): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($value), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
    }

    /**
     * @return array<string, true>
     */
    public static function load(): array
    {
        if (static::$set !== null) {
            return static::$set;
        }

        $path = static::path();

        if (! is_file($path)) {
            static::build();
        }

        try {
            $set = require $path;
        } catch (Throwable) {
            $set = [];
        }

        return static::$set = is_array($set) ? $set : [];
    }

    /**
     * Scans the code and writes the catalog file.
     *
     * @return int how many strings
     */
    public static function build(): int
    {
        $strings = [];

        foreach ([app_path(), config_path(), base_path('routes')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            foreach (File::allFiles($directory) as $file) {
                // Development-only demo data (sample customer names) isn't
                // AffilStack's own interface text.
                if (str_contains(str_replace('\\', '/', $file->getPathname()), '/Console/Commands/Dev/')) {
                    continue;
                }

                if ($file->getExtension() === 'php') {
                    static::collect((string) file_get_contents($file->getPathname()), $strings);
                }
            }
        }

        $compiler = app('blade.compiler');

        foreach (File::allFiles(resource_path('views')) as $file) {
            if (! str_ends_with($file->getFilename(), '.blade.php')) {
                continue;
            }

            try {
                static::collect($compiler->compileString((string) file_get_contents($file->getPathname())), $strings);
            } catch (Throwable) {
                // A template that doesn't compile on its own adds nothing.
            }
        }

        $hashes = [];

        foreach (array_keys($strings) as $string) {
            $hashes[md5($string)] = true;
        }

        File::ensureDirectoryExists(dirname(static::path()));

        foreach (File::glob(storage_path('framework/translation-catalog-*.php')) as $old) {
            File::delete($old);
        }

        File::put(static::path(), '<?php return '.var_export($hashes, true).';'.PHP_EOL);
        static::$set = $hashes;

        return count($hashes);
    }

    /**
     * @param  array<string, true>  $strings
     */
    protected static function collect(string $php, array &$strings): void
    {
        foreach (@token_get_all($php) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $quote = $token[1][0];
            $inner = substr($token[1], 1, -1);
            $value = $quote === "'" ? str_replace(["\\'", '\\\\'], ["'", '\\'], $inner) : stripcslashes($inner);
            $value = static::normalize($value);

            if (static::looksLikeText($value)) {
                $strings[$value] = true;
            }
        }
    }

    protected static function looksLikeText(string $value): bool
    {
        $length = mb_strlen($value);

        if ($length < 2 || $length > 400 || ! preg_match('/\p{L}/u', $value)) {
            return false;
        }

        // Keys, identifiers, paths, class names, URLs, CSS classes, SQL.
        if (preg_match('#^[a-z0-9_.\-/:\#@*]+$#', $value) || str_contains($value, '://') || str_contains($value, '\\') || str_starts_with($value, '/')) {
            return false;
        }

        return ! preg_match('/^(select|insert|update|delete) /i', $value);
    }

    /**
     * The catalog file for this deploy: a fresh build (new autoloader) or a
     * new view/config change gets a new file.
     */
    public static function path(): string
    {
        $fingerprint = Cache::remember('translation-catalog-fingerprint', 60, fn () => static::fingerprint());

        return storage_path('framework/translation-catalog-'.$fingerprint.'.php');
    }

    protected static function fingerprint(): string
    {
        $latest = 0;

        foreach ([app_path(), config_path(), resource_path('views'), base_path('routes')] as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            /** @var SplFileInfo $file */
            foreach (File::allFiles($directory) as $file) {
                $latest = max($latest, $file->getMTime());
            }
        }

        return substr(md5($latest.'|'.(is_file(base_path('vendor/composer/autoload_real.php')) ? filemtime(base_path('vendor/composer/autoload_real.php')) : 0)), 0, 12);
    }

    /**
     * Forgets the in-memory copy (tests, after a rebuild).
     */
    public static function flush(): void
    {
        static::$set = null;
        Cache::forget('translation-catalog-fingerprint');
    }
}
