<?php

namespace App\Services\Media;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Makes every featured-company logo the same size: trims the transparent
 * or white margin around the artwork, scales it to fit a uniform box
 * (BOX_WIDTH × BOX_HEIGHT, keeping its proportions) and centres it on a
 * transparent canvas of exactly that size, saved as WebP. The homepage
 * logo row then lines up no matter how the original file was cropped.
 * Runs automatically when a BrandLogo is saved; `brand-logos:normalize`
 * reprocesses the ones uploaded before this existed.
 */
class LogoNormalizer
{
    public const BOX_WIDTH = 320;

    public const BOX_HEIGHT = 120;

    /**
     * Inner margin so a logo never touches the canvas edge.
     */
    protected const PADDING = 8;

    public const SUFFIX = '-logo.webp';

    public static function isNormalized(?string $path): bool
    {
        return is_string($path) && str_ends_with($path, self::SUFFIX);
    }

    /**
     * @return string the normalized file's path on the disk, or $path
     *                unchanged when the file can't be read as a raster image
     */
    public function normalize(string $diskName, string $path): string
    {
        $disk = Storage::disk($diskName);

        if (static::isNormalized($path) || ! $disk->exists($path)) {
            return $path;
        }

        $contents = $disk->get($path);
        $size = $contents ? @getimagesizefromstring($contents) : false;

        if (! $size || ($size[0] * $size[1]) > 40_000_000) {
            return $path;
        }

        $source = @imagecreatefromstring($contents);

        if (! $source instanceof GdImage) {
            return $path;
        }

        imagepalettetotruecolor($source);
        $source = $this->downscale($source, 1600);
        [$x, $y, $width, $height] = $this->contentBox($source);

        $innerWidth = self::BOX_WIDTH - 2 * self::PADDING;
        $innerHeight = self::BOX_HEIGHT - 2 * self::PADDING;
        $scale = min($innerWidth / $width, $innerHeight / $height);
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor(self::BOX_WIDTH, self::BOX_HEIGHT);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);
        imagefill($canvas, 0, 0, imagecolorallocatealpha($canvas, 0, 0, 0, 127));
        imagecopyresampled(
            $canvas, $source,
            intdiv(self::BOX_WIDTH - $targetWidth, 2), intdiv(self::BOX_HEIGHT - $targetHeight, 2),
            $x, $y, $targetWidth, $targetHeight, $width, $height,
        );
        imagesavealpha($canvas, true);

        ob_start();
        imagewebp($canvas, null, 90);
        $webp = (string) ob_get_clean();

        $directory = trim(pathinfo($path, PATHINFO_DIRNAME), '.');
        $newPath = ($directory !== '' ? $directory.'/' : '').Str::slug(pathinfo($path, PATHINFO_FILENAME)).'-'.Str::lower(Str::random(6)).self::SUFFIX;
        $disk->put($newPath, $webp, 'public');

        return $newPath;
    }

    protected function downscale(GdImage $image, int $max): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);

        if (max($width, $height) <= $max) {
            return $image;
        }

        $scale = $max / max($width, $height);
        $resized = imagecreatetruecolor((int) round($width * $scale), (int) round($height * $scale));
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, imagesx($resized), imagesy($resized), $width, $height);

        return $resized;
    }

    /**
     * The bounding box of the "ink": pixels that are neither (nearly)
     * transparent nor (nearly) white. Falls back to the whole image.
     *
     * @return array{0: int, 1: int, 2: int, 3: int} x, y, width, height
     */
    protected function contentBox(GdImage $image): array
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $minX = $width;
        $minY = $height;
        $maxX = -1;
        $maxY = -1;

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba >> 24) & 0x7F;
                $red = ($rgba >> 16) & 0xFF;
                $green = ($rgba >> 8) & 0xFF;
                $blue = $rgba & 0xFF;

                if ($alpha > 110 || ($red > 244 && $green > 244 && $blue > 244)) {
                    continue;
                }

                $minX = min($minX, $x);
                $maxX = max($maxX, $x);
                $minY = min($minY, $y);
                $maxY = max($maxY, $y);
            }
        }

        if ($maxX < 0) {
            return [0, 0, $width, $height];
        }

        return [$minX, $minY, $maxX - $minX + 1, $maxY - $minY + 1];
    }
}
