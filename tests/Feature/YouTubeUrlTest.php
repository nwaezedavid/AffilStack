<?php

namespace Tests\Feature;

use App\Models\HomepageFeature;
use App\Support\YouTubeUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "Indicate where I can add the YouTube video link so that it will just show
 * up on the frontend" — an admin pastes whatever link they have, so every
 * shape YouTube hands out has to resolve to the same video.
 */
class YouTubeUrlTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function videoLinks(): array
    {
        return [
            'watch' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ'],
            'watch with extra params' => ['https://www.youtube.com/watch?feature=share&v=dQw4w9WgXcQ&t=42s'],
            'mobile site' => ['https://m.youtube.com/watch?v=dQw4w9WgXcQ'],
            'music site' => ['https://music.youtube.com/watch?v=dQw4w9WgXcQ&list=RD'],
            'short link' => ['https://youtu.be/dQw4w9WgXcQ?si=abc123'],
            'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ'],
            'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ?rel=0'],
            'nocookie embed' => ['https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ'],
            'live' => ['https://www.youtube.com/live/dQw4w9WgXcQ'],
            'old v path' => ['http://youtube.com/v/dQw4w9WgXcQ'],
            'no scheme' => ['youtube.com/watch?v=dQw4w9WgXcQ'],
            'bare id' => ['dQw4w9WgXcQ'],
            'padded with spaces' => ['  https://youtu.be/dQw4w9WgXcQ  '],
        ];
    }

    #[DataProvider('videoLinks')]
    public function test_every_youtube_link_shape_resolves_to_the_video_id(string $url): void
    {
        $this->assertSame('dQw4w9WgXcQ', YouTubeUrl::idFrom($url));
    }

    public function test_non_youtube_links_are_rejected(): void
    {
        $this->assertNull(YouTubeUrl::idFrom(null));
        $this->assertNull(YouTubeUrl::idFrom(''));
        $this->assertNull(YouTubeUrl::idFrom('https://vimeo.com/123456789'));
        $this->assertNull(YouTubeUrl::idFrom('https://www.youtube.com/@affilstack'));
        $this->assertNull(YouTubeUrl::idFrom('https://evil.example/watch?v=dQw4w9WgXcQ'));
    }

    public function test_embeds_use_the_privacy_enhanced_domain_and_thumbnails_come_from_youtube(): void
    {
        $this->assertStringStartsWith('https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ?', YouTubeUrl::embedUrl('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertStringContainsString('autoplay=1', YouTubeUrl::embedUrl('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertStringNotContainsString('autoplay', YouTubeUrl::embedUrl('https://youtu.be/dQw4w9WgXcQ', autoplay: false));
        $this->assertSame('https://i.ytimg.com/vi/dQw4w9WgXcQ/hqdefault.jpg', YouTubeUrl::thumbnailUrl('https://youtu.be/dQw4w9WgXcQ'));
        $this->assertNull(YouTubeUrl::embedUrl('not a video'));
    }

    public function test_homepage_features_share_the_same_parser(): void
    {
        $this->assertSame('dQw4w9WgXcQ', HomepageFeature::youtubeIdFrom('https://www.youtube.com/shorts/dQw4w9WgXcQ'));
        $this->assertSame(YouTubeUrl::embedUrl('https://youtu.be/dQw4w9WgXcQ'), HomepageFeature::youtubeEmbedUrlFrom('https://youtu.be/dQw4w9WgXcQ'));
    }
}
