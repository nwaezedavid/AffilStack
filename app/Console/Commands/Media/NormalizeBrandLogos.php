<?php

namespace App\Console\Commands\Media;

use App\Models\BrandLogo;
use App\Services\Media\LogoNormalizer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Re-processes every featured-company logo through LogoNormalizer — for
 * logos uploaded before automatic resizing existed. Safe to run again:
 * already-normalized logos are skipped.
 */
#[Signature('brand-logos:normalize')]
#[Description('Trim and resize every homepage company logo to the same box')]
class NormalizeBrandLogos extends Command
{
    public function handle(LogoNormalizer $normalizer): int
    {
        $done = 0;

        BrandLogo::query()->each(function (BrandLogo $logo) use ($normalizer, &$done): void {
            if (LogoNormalizer::isNormalized($logo->logo_path)) {
                return;
            }

            $path = $normalizer->normalize('public', $logo->logo_path);

            if ($path !== $logo->logo_path) {
                $logo->update(['logo_path' => $path]);
                $done++;
                $this->line("Resized: {$logo->name}");
            } else {
                $this->warn("Skipped (not a readable image): {$logo->name}");
            }
        });

        $this->info("{$done} logo(s) resized.");

        return self::SUCCESS;
    }
}
