<?php

namespace App\Filament\Support;

use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The counterpart to WebpFileUpload for images that are read by crawlers
 * rather than shown on a page: the square site icon (turned into the
 * favicon set) and social share images. These are stored exactly as
 * uploaded — Google's favicon guidelines list no WebP support, and
 * LinkedIn and several messaging apps still ignore WebP og:images — while
 * applying the same raster-only safety check (no SVG, no disguised files).
 */
class OriginalImageUpload
{
    public static function make(string $name): FileUpload
    {
        return FileUpload::make($name)
            ->image()
            ->disk('public')
            ->maxSize(5120)
            ->saveUploadedFileUsing(function (BaseFileUpload $component, TemporaryUploadedFile $file): ?string {
                $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

                if (! in_array($file->getMimeType(), $allowedMimes, true)
                    || ! in_array(strtolower($file->getClientOriginalExtension()), $allowedExtensions, true)) {
                    throw ValidationException::withMessages([
                        $component->getStatePath() => 'Upload a PNG or JPG image.',
                    ]);
                }

                return $component->saveUploadedFile($file);
            });
    }
}
