<?php

namespace App\Services\Extension;

use App\Http\Controllers\Dashboard\ExtensionController;
use App\Services\Seo\FaviconGenerator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

/**
 * Builds the Chrome extension zip from resources/browser-extension:
 *   - the customer download (inside an "affilstack-extension/" folder,
 *     config.js pointed at this site's own API), and
 *   - the Chrome Web Store upload (manifest at the zip root, the store's
 *     default https://affilstack.com/api left as is).
 * Both replace the toolbar icons with the site's uploaded square logo
 * (Admin → SEO → Site icon) when one exists, so the extension carries
 * the AffilStack brand rather than the built-in monogram.
 */
class ExtensionPackager
{
    public const ICON_SIZES = [16, 32, 48, 128];

    public function __construct(
        protected FaviconGenerator $favicons,
    ) {}

    public function build(string $zipPath, bool $forStore = false): string
    {
        $sourceDir = resource_path('browser-extension');
        $prefix = $forStore ? '' : 'affilstack-extension/';
        $icons = $this->brandedIcons();

        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the extension zip.');
        }

        /** @var SplFileInfo $file */
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($sourceDir) + 1));

            // store/ holds the Web Store listing texts and README is for
            // developers — neither belongs in the installed extension.
            if (str_starts_with($relative, 'store/') || ($forStore && $relative === 'README.md')) {
                continue;
            }

            if (isset($icons[$relative])) {
                $zip->addFromString($prefix.$relative, $icons[$relative]);

                continue;
            }

            if ($relative === 'config.js' && ! $forStore) {
                $zip->addFromString($prefix.$relative, str_replace(
                    ExtensionController::STORE_BUILD_API_BASE_URL,
                    ExtensionController::apiBaseUrl(),
                    (string) file_get_contents($file->getPathname()),
                ));

                continue;
            }

            $zip->addFile($file->getPathname(), $prefix.$relative);
        }

        $zip->close();

        return $zipPath;
    }

    public function version(): string
    {
        $manifest = json_decode((string) file_get_contents(resource_path('browser-extension/manifest.json')), true);

        return (string) ($manifest['version'] ?? '0.0.0');
    }

    /**
     * @return array<string, string> relative path => PNG bytes
     */
    protected function brandedIcons(): array
    {
        $icons = [];

        foreach (self::ICON_SIZES as $size) {
            $png = $this->favicons->uploadedIconPng($size);

            if ($png === null) {
                return [];
            }

            $icons["icons/icon{$size}.png"] = $png;
        }

        return $icons;
    }
}
