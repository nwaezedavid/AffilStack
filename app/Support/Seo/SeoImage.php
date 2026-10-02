<?php

namespace App\Support\Seo;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Resolves an uploaded image path on the public disk into what social and
 * search crawlers need: an absolute URL in a format every one of them
 * accepts, plus its pixel size.
 *
 * Earlier uploads went through WebpFileUpload, which stores a .webp
 * sibling but keeps the original JPG/PNG on disk. LinkedIn and some
 * messaging apps still ignore WebP og:images and Google's favicon/logo
 * guidelines list no WebP support, so whenever the original still exists
 * next to the .webp it is preferred here.
 */
final class SeoImage
{
    protected const ORIGINAL_EXTENSIONS = ['png', 'jpg', 'jpeg', 'gif'];

    public static function preferredPath(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'webp') {
            return $path;
        }

        $disk = Storage::disk('public');
        $base = Str::beforeLast($path, '.');

        foreach (self::ORIGINAL_EXTENSIONS as $extension) {
            if ($disk->exists("{$base}.{$extension}")) {
                return "{$base}.{$extension}";
            }
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        $preferred = self::preferredPath($path);

        return $preferred ? Storage::disk('public')->url($preferred) : null;
    }

    /**
     * @return array{width: int, height: int}|null
     */
    public static function dimensions(?string $path): ?array
    {
        $preferred = self::preferredPath($path);

        if ($preferred === null) {
            return null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($preferred)) {
            return null;
        }

        // Upload paths are random and never rewritten in place, so the size
        // of a given path never changes — safe to cache indefinitely.
        $size = Cache::rememberForever('seo_image_size:'.md5($preferred), function () use ($disk, $preferred) {
            $info = @getimagesizefromstring((string) $disk->get($preferred));

            return $info ? ['width' => $info[0], 'height' => $info[1]] : false;
        });

        return $size ?: null;
    }

    public static function mimeType(?string $path): ?string
    {
        $preferred = self::preferredPath($path);

        return match (strtolower((string) pathinfo((string) $preferred, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => null,
        };
    }
}
