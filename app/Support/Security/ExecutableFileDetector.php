<?php

namespace App\Support\Security;

/**
 * Spots files a web server could run as code: PHP-like names (including
 * "photo.php.jpg" double extensions), server config files that could
 * switch PHP back on (.htaccess, .user.ini), and PHP code hidden inside an
 * otherwise ordinary file. Shared by the upload guard and Tom's daily
 * web-shell scan so both use the same rules.
 */
class ExecutableFileDetector
{
    protected const CHUNK = 1048576;

    protected const CONFIG_FILES = ['.htaccess', '.user.ini', 'php.ini', '.php.ini', 'web.config'];

    public static function nameIsExecutable(string $fileName): bool
    {
        $name = strtolower(basename($fileName));
        $blocked = array_map('strtolower', (array) config('security.executable_extensions', []));

        if (in_array($name, self::CONFIG_FILES, true)) {
            return true;
        }

        return array_intersect(array_slice(explode('.', $name), 1), $blocked) !== [];
    }

    public static function extensionIsExecutable(?string $extension): bool
    {
        return $extension !== null && in_array(strtolower($extension), array_map('strtolower', (array) config('security.executable_extensions', [])), true);
    }

    /**
     * Looks for PHP code in a file, reading it in chunks with a small
     * overlap so a tag split across two chunks is still found. "<?php"
     * counts anywhere; the short tags "<?=" and "<? " can occur by chance
     * in binary data, so they only count in text files.
     */
    public static function containsPhp(string $path, ?int $maxBytes = null): bool
    {
        $maxBytes ??= (int) config('security.upload_content_scan_max_bytes');

        if (! is_file($path) || filesize($path) > $maxBytes) {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $tail = '';
        $first = true;

        try {
            while (! feof($handle)) {
                $chunk = $tail.(string) fread($handle, self::CHUNK);

                if (preg_match('/<\?php[\s\/]/i', $chunk) === 1) {
                    return true;
                }

                $isText = ! str_contains($chunk, "\0");

                if ($isText && (str_contains($chunk, '<?=') || ($first && preg_match('/^\s*<\?\s/', $chunk) === 1))) {
                    return true;
                }

                $tail = substr($chunk, -8);
                $first = false;
            }
        } finally {
            fclose($handle);
        }

        return false;
    }
}
