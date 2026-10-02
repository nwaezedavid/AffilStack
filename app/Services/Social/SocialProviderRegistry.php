<?php

namespace App\Services\Social;

use InvalidArgumentException;

/**
 * Every social platform a user can connect on Connected Accounts, with the
 * user-facing copy that explains what connecting each one unlocks. The one
 * place a new provider is added — the controller, the daily re-check
 * command and <x-connections.publish-status> all read from here.
 */
class SocialProviderRegistry
{
    /**
     * @var array<string, class-string<SocialOAuthProvider>>
     */
    protected const PROVIDERS = [
        'youtube' => YouTubePublishingService::class,
        'linkedin' => LinkedInOAuthService::class,
        'tiktok' => TikTokPublishingService::class,
        'instagram' => InstagramPublishingService::class,
    ];

    /**
     * @return array<string, SocialOAuthProvider>
     */
    public function all(): array
    {
        return collect(self::PROVIDERS)->map(fn (string $class) => app($class))->all();
    }

    /**
     * @return array<int, string>
     */
    public function keys(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public function has(string $key): bool
    {
        return isset(self::PROVIDERS[$key]);
    }

    public function get(string $key): SocialOAuthProvider
    {
        if (! $this->has($key)) {
            throw new InvalidArgumentException("Unknown social provider [{$key}].");
        }

        return app(self::PROVIDERS[$key]);
    }

    /**
     * Display copy for one provider: its name, the provider-styled button
     * (<x-ui.oauth-button>) to connect with, what connecting enables, and the
     * permissions it asks for in plain words.
     *
     * @return array{label: string, brand: string, button_brand: string, button_label: string, tagline: string, enables: array<int, string>, permissions: array<int, string>, via: ?string}
     */
    public function meta(string $key): array
    {
        return match ($key) {
            'youtube' => [
                'label' => 'YouTube',
                'brand' => 'youtube',
                // YouTube connects through Google OAuth, so the button follows
                // Google's sign-in branding (white, four-colour G).
                'button_brand' => 'google',
                'button_label' => 'Continue with Google',
                'tagline' => 'Publish your rendered UGC videos straight to your YouTube channel — no downloading and re-uploading.',
                'enables' => [
                    'One-click "Publish to YouTube" on every finished UGC video',
                    'Titles and descriptions filled in from your generated script',
                    'Your channel name and picture shown here so you always know where videos go',
                ],
                'permissions' => ['Upload videos to your channel', 'See your channel name and picture'],
                'via' => 'Signs in with the Google account that owns your channel',
            ],
            'linkedin' => [
                'label' => 'LinkedIn',
                'brand' => 'linkedin',
                'button_brand' => 'linkedin',
                'button_label' => 'Connect LinkedIn',
                'tagline' => 'Export your generated LinkedIn posts and articles under your real LinkedIn identity. AffilStack never posts or messages for you.',
                'enables' => [
                    'One-click export of generated LinkedIn posts and articles',
                    'Content prepared in the context of your named LinkedIn profile',
                    'Identity only — no posting or messaging permission is ever requested',
                ],
                'permissions' => ['Your name and profile picture', 'Your email address'],
                'via' => null,
            ],
            'tiktok' => [
                'label' => 'TikTok',
                'brand' => 'tiktok',
                'button_brand' => 'tiktok',
                'button_label' => 'Continue with TikTok',
                'tagline' => 'Send your rendered UGC videos to your TikTok account in one click, with the caption already written.',
                'enables' => [
                    'One-click "Publish to TikTok" on every finished UGC video',
                    'Captions pulled from your generated TikTok script',
                    'TikTok fetches the video itself — nothing to download',
                ],
                'permissions' => ['Your display name and avatar', 'Post videos to your account'],
                'via' => null,
            ],
            'instagram' => [
                'label' => 'Instagram',
                'brand' => 'instagram',
                'button_brand' => 'instagram',
                'button_label' => 'Connect Instagram',
                'tagline' => 'Publish your rendered UGC videos to Instagram as Reels from the Instagram professional account linked to your Facebook Page.',
                'enables' => [
                    'One-click "Publish to Instagram" as a Reel',
                    'Captions pulled from your generated Instagram copy',
                    'Works with Instagram Business and Creator accounts',
                ],
                'permissions' => ['See your Facebook Pages', 'Read your Instagram professional profile', 'Publish Reels to it'],
                'via' => 'Uses Facebook Login — your Instagram professional account must be linked to a Facebook Page',
            ],
            default => throw new InvalidArgumentException("Unknown social provider [{$key}]."),
        };
    }
}
