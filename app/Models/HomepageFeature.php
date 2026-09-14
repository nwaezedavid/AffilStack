<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A feature card on the public homepage. Admin-managed (Filament, "Content"
 * group) so the page can showcase every module without a code deploy.
 */
#[Fillable(['title', 'description', 'icon', 'media_type', 'image_path', 'youtube_url', 'sort_order', 'is_active'])]
class HomepageFeature extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function youtubeEmbedUrl(): ?string
    {
        return static::youtubeEmbedUrlFrom($this->youtube_url);
    }

    public function youtubeThumbnailUrl(): ?string
    {
        return static::youtubeThumbnailUrlFrom($this->youtube_url);
    }

    /**
     * Accepts any common YouTube URL shape (watch?v=, youtu.be/, shorts/,
     * embed/, with or without extra query params) and returns the video ID,
     * or null if the value isn't recognizable.
     *
     * Static and reused outside this model (e.g. the homepage hero, which
     * stores its own YouTube URL as a SiteSetting rather than a row here)
     * so there's exactly one place that understands YouTube's URL shapes.
     */
    public static function youtubeIdFrom(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $patterns = [
            '~youtu\.be/([A-Za-z0-9_-]{6,})~',
            '~youtube\.com/watch\?[^#]*v=([A-Za-z0-9_-]{6,})~',
            '~youtube\.com/shorts/([A-Za-z0-9_-]{6,})~',
            '~youtube\.com/embed/([A-Za-z0-9_-]{6,})~',
            '~youtube\.com/live/([A-Za-z0-9_-]{6,})~',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * A ready-to-embed URL, or null if the value isn't recognizable —
     * callers should fall back to a plain link in that case rather than
     * embedding.
     */
    public static function youtubeEmbedUrlFrom(?string $url): ?string
    {
        $id = static::youtubeIdFrom($url);

        return $id ? 'https://www.youtube-nocookie.com/embed/'.$id.'?autoplay=1' : null;
    }

    /**
     * YouTube's own thumbnail CDN — used to render a lightweight
     * click-to-play facade instead of loading every embedded video's
     * iframe up front (keeps the homepage fast on first load).
     */
    public static function youtubeThumbnailUrlFrom(?string $url): ?string
    {
        $id = static::youtubeIdFrom($url);

        return $id ? 'https://img.youtube.com/vi/'.$id.'/hqdefault.jpg' : null;
    }
}
