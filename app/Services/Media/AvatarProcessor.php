<?php

namespace App\Services\Media;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Profile photos: centre-crops any uploaded JPG/PNG/GIF/WebP to a square,
 * scales it to 512×512 and stores it as WebP on the public disk — with
 * GD only (no image package), so it works on Hostinger as-is. Re-encoding
 * also strips EXIF data (GPS location etc.) from phone photos.
 */
class AvatarProcessor
{
    public const SIZE = 512;

    protected const MAX_PIXELS = 40_000_000;

    /**
     * @return string the stored path on the public disk
     */
    public function store(UploadedFile $file, int $userId): string
    {
        $contents = (string) file_get_contents($file->getRealPath());
        $size = @getimagesizefromstring($contents);

        if (! $size || ($size[0] * $size[1]) > self::MAX_PIXELS) {
            throw new RuntimeException('That image is too large or not a valid picture.');
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('That image could not be read.');
        }

        $source = $this->applyExifOrientation($source, $contents);

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);
        $target = min(self::SIZE, $side);

        $square = imagecreatetruecolor($target, $target);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagefill($square, 0, 0, imagecolorallocatealpha($square, 255, 255, 255, 127));

        imagecopyresampled(
            $square,
            $source,
            0,
            0,
            intdiv($width - $side, 2),
            intdiv($height - $side, 2),
            $target,
            $target,
            $side,
            $side,
        );

        ob_start();
        imagewebp($square, null, 85);
        $webp = (string) ob_get_clean();

        imagedestroy($source);
        imagedestroy($square);

        if ($webp === '') {
            throw new RuntimeException('That image could not be converted.');
        }

        $path = 'avatars/'.$userId.'-'.Str::lower(Str::random(12)).'.webp';
        Storage::disk('public')->put($path, $webp, 'public');

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && str_starts_with($path, 'avatars/')) {
            Storage::disk('public')->delete($path);
        }
    }

    /**
     * Phone cameras save portrait shots sideways plus an EXIF "rotate me"
     * flag that GD ignores — honour it so selfies aren't cropped sideways.
     */
    protected function applyExifOrientation(\GdImage $image, string $contents): \GdImage
    {
        if (! function_exists('exif_read_data') || ! str_starts_with($contents, "\xFF\xD8")) {
            return $image;
        }

        $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($contents));
        $angle = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => 180,
            6 => -90,
            8 => 90,
            default => 0,
        };

        if ($angle === 0) {
            return $image;
        }

        $rotated = imagerotate($image, $angle, 0);

        return $rotated === false ? $image : $rotated;
    }
}
