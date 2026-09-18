<?php

namespace App\Console\Commands;

use App\Models\BrandLogo;
use App\Models\HomepageFeature;
use App\Models\SitePage;
use App\Models\SiteSetting;
use App\Models\Testimonial;
use App\Services\Media\WebpImageConverter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * The one-off half of image optimization (see WebpFileUpload for the
 * going-forward half, applied automatically to every new upload): walks
 * every image ever uploaded through Filament before WebpFileUpload
 * existed and converts each to WebP in place, updating the stored path —
 * originals are kept on disk untouched, exactly like WebpImageConverter
 * does for a fresh upload. Safe to run more than once: WebpImageConverter
 * already treats a path it previously converted (now ending .webp) as a
 * no-op.
 */
#[Signature('images:optimize-to-webp')]
#[Description('One-off: convert every already-uploaded site image (pages, testimonials, brand logos, homepage features, branding, SEO) to WebP')]
class OptimizeExistingImagesToWebp extends Command
{
    /**
     * Every model column that stores an uploaded image path on the
     * 'public' disk, via a WebpFileUpload field.
     *
     * @var array<class-string, array<int, string>>
     */
    protected const MODEL_COLUMNS = [
        SitePage::class => ['og_image_path'],
        Testimonial::class => ['avatar_path'],
        BrandLogo::class => ['logo_path'],
        HomepageFeature::class => ['image_path'],
    ];

    /** Every SiteSetting key that stores an uploaded image path. */
    protected const SETTING_KEYS = [
        'logo_rectangular_path',
        'logo_square_path',
        'hero_image_path',
        'seo_og_image_path',
    ];

    public function handle(WebpImageConverter $converter): void
    {
        $converted = 0;

        foreach (self::MODEL_COLUMNS as $modelClass => $columns) {
            foreach ($columns as $column) {
                $converted += $this->convertColumn($modelClass::query(), $column, $converter);
            }
        }

        foreach (self::SETTING_KEYS as $key) {
            $converted += $this->convertSetting($key, $converter);
        }

        $this->info("Image WebP optimization: {$converted} image(s) converted (originals kept on disk).");
    }

    /**
     * @param  Builder<Model>  $query
     */
    protected function convertColumn(Builder $query, string $column, WebpImageConverter $converter): int
    {
        $count = 0;

        foreach ($query->whereNotNull($column)->where($column, '!=', '')->get() as $model) {
            $original = (string) $model->{$column};
            $converted = $converter->convert('public', $original);

            if ($converted !== $original) {
                $model->{$column} = $converted;
                $model->save();
                $count++;
            }
        }

        return $count;
    }

    protected function convertSetting(string $key, WebpImageConverter $converter): int
    {
        $original = SiteSetting::get($key);

        if (! $original) {
            return 0;
        }

        $converted = $converter->convert('public', $original);

        if ($converted === $original) {
            return 0;
        }

        SiteSetting::set($key, $converted);

        return 1;
    }
}
