<?php

namespace App\Services\Social;

use App\Models\Generation;
use App\Models\SocialConnection;
use App\Models\TikTokSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Connect TikTok" + real publishing (task #2) via TikTok's Content
 * Posting API. Every new TikTok app defaults to a capped, private-only
 * scope until TikTok audits it — TikTokSetting::isApprovedForPublishing()
 * gates whether this attempts a real (public) publish at all;
 * publish() refuses outright until approved, so
 * Dashboard\SocialPublishController falls back to "download and post
 * manually" instead.
 *
 * Publishing here uses source PULL_FROM_URL (TikTok fetches the video
 * itself from Generation::output, already a public URL — see
 * UgcVideoService) rather than uploading raw bytes through AffilStack,
 * which keeps this a single API call instead of a multi-step upload.
 * TikTok's own publish pipeline finishes asynchronously after this call
 * acknowledges it — the video appears in the user's TikTok inbox/drafts
 * shortly after, not instantly.
 */
class TikTokPublishingService implements SocialPublishProvider
{
    protected const AUTHORIZATION_ENDPOINT = 'https://www.tiktok.com/v2/auth/authorize/';

    protected const TOKEN_ENDPOINT = 'https://open.tiktokapis.com/v2/oauth/token/';

    protected const USERINFO_ENDPOINT = 'https://open.tiktokapis.com/v2/user/info/';

    protected const PUBLISH_INIT_ENDPOINT = 'https://open.tiktokapis.com/v2/post/publish/video/init/';

    protected const SCOPE = 'user.info.basic,video.publish';

    protected TikTokSetting $settings;

    public function __construct()
    {
        $this->settings = TikTokSetting::current();
    }

    public function key(): string
    {
        return 'tiktok';
    }

    public function isAvailable(): bool
    {
        return $this->settings->isAvailable();
    }

    public function isApprovedForPublishing(): bool
    {
        return $this->settings->isApprovedForPublishing();
    }

    protected function clientKey(): string
    {
        return (string) $this->settings->credential('client_key');
    }

    protected function clientSecret(): string
    {
        return (string) $this->settings->credential('client_secret');
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_key' => $this->clientKey(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $token = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            throw new RuntimeException('TikTok token exchange failed: '.$token->body());
        }

        if (! $token->json('refresh_token')) {
            throw new RuntimeException('TikTok did not return a refresh token — please try connecting again.');
        }

        $profile = Http::withToken($token->json('access_token'))
            ->get(self::USERINFO_ENDPOINT, ['fields' => 'open_id,display_name']);

        $user = $profile->json('data.user');

        if ($profile->failed() || ! $user) {
            throw new RuntimeException('Could not read the connected TikTok profile: '.$profile->body());
        }

        return [
            'credentials' => [
                'access_token' => $token->json('access_token'),
                'refresh_token' => $token->json('refresh_token'),
                'expires_at' => now()->addSeconds((int) $token->json('expires_in', 3600))->toIso8601String(),
            ],
            'account_name' => (string) ($user['display_name'] ?? 'TikTok account'),
            'account_id' => (string) ($user['open_id'] ?? ''),
        ];
    }

    protected function freshAccessToken(SocialConnection $connection): string
    {
        $expiresAt = $connection->credential('expires_at');

        if ($expiresAt && now()->lt($expiresAt) && $connection->credential('access_token')) {
            return (string) $connection->credential('access_token');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_key' => $this->clientKey(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $connection->credential('refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Could not refresh the TikTok connection — it may have been revoked. Please reconnect TikTok.');
        }

        $accessToken = (string) $response->json('access_token');

        $connection->update([
            'credentials' => [
                ...$connection->credentials,
                'access_token' => $accessToken,
                'refresh_token' => $response->json('refresh_token', $connection->credential('refresh_token')),
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
            ],
        ]);

        return $accessToken;
    }

    public function publish(SocialConnection $connection, Generation $generation, string $title, string $caption): array
    {
        if (! $this->isApprovedForPublishing()) {
            throw new RuntimeException("TikTok hasn't approved this app for public posting yet — download the video and post it manually.");
        }

        if (! $generation->output) {
            throw new RuntimeException('This video has no file to publish yet.');
        }

        $accessToken = $this->freshAccessToken($connection);

        $response = Http::withToken($accessToken)->post(self::PUBLISH_INIT_ENDPOINT, [
            'post_info' => [
                'title' => $caption,
                'privacy_level' => 'PUBLIC_TO_EVERYONE',
                'disable_duet' => false,
                'disable_comment' => false,
                'disable_stitch' => false,
            ],
            'source_info' => [
                'source' => 'PULL_FROM_URL',
                'video_url' => $generation->output,
            ],
        ]);

        if ($response->failed() || ! $response->json('data.publish_id')) {
            throw new RuntimeException('TikTok rejected the publish request: '.$response->body());
        }

        return ['url' => null, 'external_id' => (string) $response->json('data.publish_id')];
    }
}
