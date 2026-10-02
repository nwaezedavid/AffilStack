<?php

namespace App\Support;

/**
 * The single place that understands YouTube's many URL shapes. An admin can
 * paste whatever their browser or the YouTube app's "Share" button gave
 * them — watch?v=, youtu.be/, /shorts/, /embed/, /live/, /v/, the
 * mobile/music subdomains, a youtube-nocookie.com embed link, or even a
 * bare 11-character video ID — and it resolves to the same video.
 *
 * Embeds always go through youtube-nocookie.com (privacy-enhanced mode: no
 * tracking cookies until the visitor actually presses play), and pages show
 * YouTube's own thumbnail as a click-to-play facade first so a homepage
 * never loads a heavy iframe up front.
 */
class YouTubeUrl
{
    /**
     * @return string|null the 11-character video ID, or null when unrecognisable
     */
    public static function idFrom(?string $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '') {
            return null;
        }

        // A bare video ID pasted on its own.
        if (preg_match('~^[A-Za-z0-9_-]{11}$~', $url)) {
            return $url;
        }

        $host = '(?:[a-z0-9-]+\.)?(?:youtube\.com|youtube-nocookie\.com)';

        $patterns = [
            '~(?:^|//)(?:www\.)?youtu\.be/([A-Za-z0-9_-]{11})~i',
            '~(?:^|//)'.$host.'/(?:watch|attribution_link)\?(?:[^#]*&)?v=([A-Za-z0-9_-]{11})~i',
            '~(?:^|//)'.$host.'/(?:shorts|embed|live|v|e)/([A-Za-z0-9_-]{11})~i',
            // attribution_link?u=/watch%3Fv%3DID — the URL-encoded form.
            '~(?:^|//)'.$host.'/attribution_link\?[^#]*v%3D([A-Za-z0-9_-]{11})~i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * A privacy-enhanced embed URL that starts playing as soon as the facade
     * swaps it in (the visitor already clicked play), or null.
     */
    public static function embedUrl(?string $url, bool $autoplay = true): ?string
    {
        $id = static::idFrom($url);

        if (! $id) {
            return null;
        }

        return 'https://www.youtube-nocookie.com/embed/'.$id.'?rel=0&modestbranding=1'.($autoplay ? '&autoplay=1' : '');
    }

    /**
     * YouTube's own thumbnail CDN. "hqdefault" exists for every video (the
     * higher-resolution "maxresdefault" doesn't), so it's the safe default.
     */
    public static function thumbnailUrl(?string $url, string $quality = 'hqdefault'): ?string
    {
        $id = static::idFrom($url);

        return $id ? 'https://i.ytimg.com/vi/'.$id.'/'.$quality.'.jpg' : null;
    }

    public static function watchUrl(?string $url): ?string
    {
        $id = static::idFrom($url);

        return $id ? 'https://www.youtube.com/watch?v='.$id : null;
    }
}
