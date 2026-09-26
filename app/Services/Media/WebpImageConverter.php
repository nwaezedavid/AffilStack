<?php

namespace App\Services\Media;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Converts a raster image already on a disk to WebP, in the same
 * directory under the same basename — the one place both halves of image
 * optimization go through: WebpFileUpload (every future admin upload,
 * converted the moment it's saved) and OptimizeExistingImagesToWebp (a
 * one-off pass over every image already on disk before this existed).
 * WebP typically renders 25-35% smaller than an equivalent JPEG/PNG at
 * visually-identical quality, which is the whole point — every image this
 * touches is one rendered on a public page, so a smaller file is a faster
 * page load.
 *
 * Deliberately conservative about what it touches: an SVG (already tiny
 * and vector — converting to a raster format would only lose quality),
 * an animated GIF (GD's decoder only ever sees the first frame, so
 * "converting" one would silently destroy the animation), anything
 * already .webp, and anything GD can't decode at all are left completely
 * untouched, returning the original path unchanged. The original file
 * itself is NEVER deleted — the caller just starts pointing its stored
 * path at the new .webp sibling instead, so nothing is lost if a
 * conversion ever needs to be undone by hand.
 */
class WebpImageConverter
{
    /**
     * GD's own default is 80; a touch higher keeps photographic images
     * visually indistinguishable from the source while still landing well
     * under the equivalent JPEG/PNG.
     */
    protected const WEBP_QUALITY = 82;

    protected const SKIPPED_EXTENSIONS = ['svg', 'webp'];

    /**
     * @param  string  $diskName  a filesystem disk name, e.g. 'public'
     * @param  string  $path  the file's existing path on that disk
     * @return string the new .webp path, or the original $path unchanged
     *                if this file was skipped for any of the reasons above
     */
    public function convert(string $diskName, string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, self::SKIPPED_EXTENSIONS, true)) {
            return $path;
        }

        $disk = Storage::disk($diskName);

        if (! $disk->exists($path)) {
            return $path;
        }

        $contents = $disk->get($path);

        if ($contents === null || $contents === '' || $this->isAnimatedGif($contents)) {
            return $path;
        }

        $webp = $this->encodeToWebp($contents);

        if ($webp === null) {
            return $path;
        }

        $webpPath = $this->availableWebpPath($disk, $path);

        $disk->put($webpPath, $webp, 'public');

        return $webpPath;
    }

    /**
     * @return non-empty-string|null the encoded WebP bytes, or null if GD
     *                               couldn't decode $contents as an image
     */
    protected function encodeToWebp(string $contents): ?string
    {
        // Refuse images whose decoded size could exhaust memory (a small
        // file can declare enormous dimensions) — kept as the original.
        $size = @getimagesizefromstring($contents);

        if (! $size || ($size[0] * $size[1]) > 40_000_000) {
            return null;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            return null;
        }

        // Indexed-color PNGs/GIFs need to become truecolor before the
        // alpha-preservation calls below have any effect.
        imagepalettetotruecolor($image);
        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        $encoded = imagewebp($image, null, self::WEBP_QUALITY);
        $webp = ob_get_clean();
        imagedestroy($image);

        if (! $encoded || $webp === false || $webp === '') {
            return null;
        }

        return $webp;
    }

    /**
     * A GIF is animated when more than one image-descriptor block appears
     * before its trailer — a cheap, well-known signature check (looking
     * for the graphic control extension block that precedes every frame)
     * that avoids pulling in a dedicated GIF-parsing library just for
     * this.
     */
    protected function isAnimatedGif(string $contents): bool
    {
        if (! str_starts_with($contents, 'GIF8')) {
            return false;
        }

        return substr_count($contents, "\x00\x21\xF9\x04") > 1;
    }

    /**
     * Same directory, same basename, .webp extension — but never
     * overwrites a file that (implausibly, given the ULID/random names
     * every upload path here gets) already happens to sit at that exact
     * path.
     */
    protected function availableWebpPath(Filesystem $disk, string $path): string
    {
        $webpPath = Str::beforeLast($path, '.').'.webp';

        if (! $disk->exists($webpPath) || $webpPath === $path) {
            return $webpPath;
        }

        return Str::beforeLast($path, '.').'-'.Str::random(6).'.webp';
    }
}
