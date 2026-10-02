<?php

namespace App\Console\Commands\Seo;

use App\Services\Seo\FaviconGenerator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * (Re)builds the favicon set from the current square site icon — the same
 * thing saving the icon in the admin does, for use after a deploy or a
 * restore. Also removes the two stale static files that used to shadow the
 * real icon and robots.txt: an empty public/favicon.ico (Laravel's
 * placeholder) and the old two-line public/robots.txt.
 */
#[Signature('seo:generate-favicons')]
#[Description('Rebuild favicon.ico, the PNG icon sizes, apple-touch-icon and site.webmanifest from the uploaded site icon')]
class GenerateFavicons extends Command
{
    public function handle(FaviconGenerator $favicons): int
    {
        $result = $favicons->generate();

        $this->components->info($result['source'] === 'upload'
            ? "Favicon set built from the uploaded icon ({$result['source_width']}×{$result['source_height']})."
            : 'No site icon uploaded yet — favicon set built from the default monogram.');

        foreach ($result['warnings'] as $warning) {
            $this->components->warn($warning);
        }

        $this->removeStaleStaticFile(public_path('favicon.ico'), fn (string $contents) => $contents === '');
        $this->removeStaleStaticFile(public_path('robots.txt'), fn (string $contents) => trim(str_replace("\r", '', $contents)) === "User-agent: *\nDisallow:");

        $this->components->twoColumnDetail('Served at', url('/favicon.ico'));

        return self::SUCCESS;
    }

    /**
     * @param  callable(string): bool  $isStale
     */
    protected function removeStaleStaticFile(string $path, callable $isStale): void
    {
        if (! is_file($path)) {
            return;
        }

        if ($isStale((string) file_get_contents($path)) && @unlink($path)) {
            $this->components->info('Removed stale '.basename($path).' from public/ — it was hiding the generated one.');

            return;
        }

        $this->components->warn('public/'.basename($path).' exists and overrides the generated one — delete it manually.');
    }
}
