<?php

namespace App\Services\WordPress;

use App\Support\SiteDomains;
use Illuminate\Support\Facades\File;
use RuntimeException;
use ZipArchive;

/**
 * Builds the installable AffilStack Connector zip from
 * resources/wordpress/affilstack-connector, with this installation's
 * address and the current version written into the plugin, and keeps the
 * result until the plugin or the address changes.
 */
class PluginPackager
{
    public static function sourcePath(): string
    {
        return resource_path('wordpress/'.config('wordpress_plugin.slug'));
    }

    public static function version(): string
    {
        return (string) config('wordpress_plugin.version');
    }

    /**
     * The address the plugin talks to: the customer app.
     */
    public static function baseUrl(): string
    {
        return rtrim(SiteDomains::appUrl() ?? (string) config('app.url'), '/');
    }

    /**
     * @throws RuntimeException
     */
    public function zipPath(): string
    {
        $slug = (string) config('wordpress_plugin.slug');
        $source = static::sourcePath();
        $fingerprint = substr(sha1(static::version().'|'.static::baseUrl().'|'.$this->sourceFingerprint($source)), 0, 12);
        $directory = storage_path('app/wordpress-plugin');
        $path = $directory.'/'.$slug.'-'.static::version().'-'.$fingerprint.'.zip';

        if (is_file($path)) {
            return $path;
        }

        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP zip extension is needed to build the WordPress plugin.');
        }

        File::ensureDirectoryExists($directory);
        foreach (File::glob($directory.'/'.$slug.'-*.zip') as $old) {
            File::delete($old);
        }

        $zip = new ZipArchive;
        $temp = $path.'.tmp';

        if ($zip->open($temp, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the WordPress plugin zip.');
        }

        foreach (File::allFiles($source) as $file) {
            $relative = $slug.'/'.str_replace('\\', '/', $file->getRelativePathname());
            $contents = (string) file_get_contents($file->getPathname());

            if (in_array($file->getExtension(), ['php', 'txt', 'js'], true)) {
                $contents = strtr($contents, [
                    '__AFFILSTACK_URL__' => static::baseUrl(),
                    '__AFFILSTACK_VERSION__' => static::version(),
                    '__AFFILSTACK_REQUIRES_WP__' => (string) config('wordpress_plugin.requires_wp'),
                    '__AFFILSTACK_TESTED_WP__' => (string) config('wordpress_plugin.tested_wp'),
                    '__AFFILSTACK_REQUIRES_PHP__' => (string) config('wordpress_plugin.requires_php'),
                ]);
            }

            $zip->addFromString($relative, $contents);
        }

        $zip->close();
        rename($temp, $path);

        return $path;
    }

    public static function fileName(): string
    {
        return config('wordpress_plugin.slug').'.zip';
    }

    protected function sourceFingerprint(string $source): string
    {
        return sha1(collect(File::allFiles($source))
            ->map(fn ($file) => $file->getRelativePathname().':'.$file->getMTime().':'.$file->getSize())
            ->sort()
            ->implode('|'));
    }
}
