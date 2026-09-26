<?php

namespace App\Filament\Support;

use App\Services\Media\WebpImageConverter;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * A drop-in replacement for FileUpload::make() — every image upload field
 * on the public site (page/SEO social-share images, testimonial photos,
 * brand-logo marquee, homepage feature images, and site branding/hero
 * images) uses this instead, so a freshly uploaded image is converted to
 * WebP the instant it's saved, without each of those six forms needing to
 * repeat that wiring themselves. See WebpImageConverter for the actual
 * conversion (and why some files are deliberately left alone), and
 * OptimizeExistingImagesToWebp for the one-off command that did the same
 * for every image uploaded before this existed.
 *
 * All the usual FileUpload methods (->image(), ->avatar(), ->disk(),
 * ->directory(), etc.) chain onto this exactly as they would onto a plain
 * FileUpload::make() — this only adds one more hook on top.
 */
class WebpFileUpload
{
    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)->saveUploadedFileUsing(
            function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                // Raster images only, checked by content AND extension: ->image()
                // accepts any image/* including SVG (which can carry script and
                // is served straight from /storage), and a PNG saved under a
                // .html name would be served as a web page.
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (! in_array($file->getMimeType(), $allowedMimes, true)
                    || ! in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions, true)) {
                    throw ValidationException::withMessages([
                        $component->getStatePath() => 'Upload a JPG, PNG, GIF or WebP image.',
                    ]);
                }

                $path = $component->saveUploadedFile($file);

                if ($path === null) {
                    return null;
                }

                return app(WebpImageConverter::class)->convert($component->getDiskName(), $path);
            }
        );
    }
}
