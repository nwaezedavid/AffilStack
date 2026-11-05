<?php

namespace App\Support\Mobile;

use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use App\Services\Modules\BlogArticleService;
use App\Services\Modules\LinkedInService;
use App\Services\Modules\XService;
use App\Services\Modules\YouTubeService;

/**
 * The AI content the phone app can create for a product — the same
 * module services (and credit prices) as the dashboard's buttons, with the
 * website route each one lives on (for the team-seat rules) and the plan
 * channel it needs, if any.
 */
class ContentTypes
{
    /**
     * @var array<string, array{label: string, description: string, route: string, channel: string|null}>
     */
    public const TYPES = [
        'blog_article' => ['label' => 'Blog article', 'description' => 'A full SEO article with your tracked link.', 'route' => 'offers.blog.store', 'channel' => null],
        'linkedin_post' => ['label' => 'LinkedIn post', 'description' => 'A ready-to-post update for your feed.', 'route' => 'offers.linkedin.post', 'channel' => null],
        'linkedin_article' => ['label' => 'LinkedIn article', 'description' => 'A long-form LinkedIn article.', 'route' => 'offers.linkedin.article', 'channel' => null],
        'x_thread' => ['label' => 'X thread', 'description' => 'A thread that sells without sounding salesy.', 'route' => 'offers.x.thread', 'channel' => 'x'],
        'youtube_script' => ['label' => 'Video script', 'description' => 'A YouTube or short-video script.', 'route' => 'offers.youtube.script', 'channel' => 'youtube'],
    ];

    /**
     * @return array<int, array{type: string, label: string, description: string, credits: int, available: bool}>
     */
    public static function forUser(User $user): array
    {
        return collect(self::TYPES)->map(fn (array $type, string $key): array => [
            'type' => $key,
            'label' => $type['label'],
            'description' => $type['description'],
            'credits' => (int) config("credits.costs.{$key}"),
            'available' => ($type['channel'] === null || $user->canUseChannel($type['channel'])) && SeatAccess::allows($user, $type['route']),
        ])->values()->all();
    }

    public static function queue(string $type, User $user, Offer $offer, ?string $keyword = null): Generation
    {
        return match ($type) {
            'blog_article' => app(BlogArticleService::class)->queue($user, $offer, $keyword),
            'linkedin_post' => app(LinkedInService::class)->queue($user, $offer, 'post'),
            'linkedin_article' => app(LinkedInService::class)->queue($user, $offer, 'article'),
            'x_thread' => app(XService::class)->queue($user, $offer),
            'youtube_script' => app(YouTubeService::class)->queue($user, $offer, 'script'),
        };
    }
}
