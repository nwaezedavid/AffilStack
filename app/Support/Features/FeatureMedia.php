<?php

namespace App\Support\Features;

use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Screenshot or animated demo, per built-in feature screenshot.
 *
 * Every built-in screenshot (public/images/features/NAME.webp) can have a
 * matching animated demo: either the bundled recording at
 * storage/app/public/feature-animations/NAME.mp4 (installed from the
 * "feature animations" zip) or a video the admin uploaded for it. Admin →
 * Website → Feature demos picks, per screenshot, which one visitors see.
 * The screenshot stays the default, the poster frame while a video loads,
 * and what visitors who prefer reduced motion see.
 *
 * Images the admin uploaded in place of a built-in screenshot are never
 * swapped — only the built-in screenshots have a matching demo.
 */
class FeatureMedia
{
    public const DIRECTORY = 'feature-animations';

    public const CHOICES_SETTING = 'feature_media_choices';

    public const VIDEOS_SETTING = 'feature_media_videos';

    public const SCREENSHOT = 'screenshot';

    public const ANIMATION = 'animation';

    /**
     * @return array<int, string> built-in screenshot names, e.g. "offer-research"
     */
    public static function names(): array
    {
        return once(fn (): array => collect(glob(public_path('images/features/*.webp')) ?: [])
            ->map(fn (string $path): string => pathinfo($path, PATHINFO_FILENAME))
            ->sort()
            ->values()
            ->all());
    }

    public static function label(string $name): string
    {
        return Str::of($name)->replace('-', ' ')->ucfirst()->replace(['Ugc', 'Api', 'Crm', 'Tiktok', 'Linkedin', 'X thread'], ['UGC', 'API', 'CRM', 'TikTok', 'LinkedIn', 'X thread'])->toString();
    }

    /**
     * The built-in screenshot an image URL points at, if it is one.
     */
    public static function nameFor(?string $src): ?string
    {
        if (! is_string($src) || ! preg_match('#/images/features/([a-z0-9-]+)\.webp(?:\?.*)?$#', $src, $match)) {
            return null;
        }

        return in_array($match[1], static::names(), true) ? $match[1] : null;
    }

    /**
     * @return array<string, string> name => screenshot|animation
     */
    public static function choices(): array
    {
        return static::decode(SiteSetting::get(self::CHOICES_SETTING));
    }

    /**
     * @return array<string, string> name => path on the public disk
     */
    public static function uploadedVideos(): array
    {
        return static::decode(SiteSetting::get(self::VIDEOS_SETTING));
    }

    public static function choiceFor(string $name): string
    {
        return (static::choices()[$name] ?? null) === self::ANIMATION ? self::ANIMATION : self::SCREENSHOT;
    }

    /**
     * @param  array<string, string>  $choices
     * @param  array<string, string|null>  $uploadedVideos
     */
    public static function save(array $choices, array $uploadedVideos): void
    {
        $names = static::names();

        $choices = collect($choices)
            ->only($names)
            ->filter(fn ($choice): bool => $choice === self::ANIMATION)
            ->all();

        $uploadedVideos = collect($uploadedVideos)
            ->only($names)
            ->filter(fn ($path): bool => static::isUploadPath($path))
            ->all();

        SiteSetting::set(self::CHOICES_SETTING, json_encode($choices));
        SiteSetting::set(self::VIDEOS_SETTING, json_encode($uploadedVideos));
    }

    /**
     * Path on the public disk of the bundled recording, when installed.
     */
    public static function bundledVideoPath(string $name): ?string
    {
        $path = self::DIRECTORY.'/'.$name.'.mp4';

        return static::fileExists($path) ? $path : null;
    }

    /**
     * Path on the public disk of the video shown for this screenshot when
     * the animation is chosen: the admin's upload, else the bundled one.
     */
    public static function videoPath(string $name): ?string
    {
        $uploaded = static::uploadedVideos()[$name] ?? null;

        if (static::isUploadPath($uploaded) && static::fileExists($uploaded)) {
            return $uploaded;
        }

        return static::bundledVideoPath($name);
    }

    /**
     * The video to show instead of an image, or null to show the image.
     *
     * @return array{url: string, type: string}|null
     */
    public static function videoFor(?string $src): ?array
    {
        $name = static::nameFor($src);

        if ($name === null || static::choiceFor($name) !== self::ANIMATION) {
            return null;
        }

        $path = static::videoPath($name);

        if ($path === null) {
            return null;
        }

        return [
            'url' => Storage::disk('public')->url($path),
            'type' => str_ends_with(strtolower($path), '.webm') ? 'video/webm' : 'video/mp4',
        ];
    }

    public static function installedCount(): int
    {
        return collect(static::names())->filter(fn (string $name): bool => static::bundledVideoPath($name) !== null)->count();
    }

    /**
     * Uploaded demo videos only ever live in feature-animations/uploads.
     */
    public static function isUploadPath(mixed $path): bool
    {
        return is_string($path)
            && preg_match('#^'.self::DIRECTORY.'/uploads/[A-Za-z0-9._-]+\.(mp4|webm)$#', $path) === 1
            && ! str_contains($path, '..');
    }

    protected static function fileExists(string $path): bool
    {
        return once(fn (): bool => Storage::disk('public')->exists($path));
    }

    /**
     * @return array<string, string>
     */
    protected static function decode(mixed $value): array
    {
        $decoded = is_string($value) ? json_decode($value, true) : $value;

        return is_array($decoded) ? array_filter($decoded, 'is_string') : [];
    }
}
