<?php

namespace App\Services\Social;

use App\Models\Generation;
use App\Models\GoogleOauthSetting;
use App\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Connect YouTube" + real publishing (task #2) — a third consent flow on
 * the same Google OAuth client as login/Gmail (scope: youtube.upload,
 * offline access so a refresh token comes back — same reasoning as
 * GmailOAuthService). Gated by GoogleOauthSetting::isYoutubeApprovedForPublishing()
 * — the YouTube Data API requires an Audit + Quota Extension before
 * videos.insert works for real users; until that's granted,
 * Dashboard\SocialPublishController falls back to "download and post
 * manually" rather than calling publish() at all.
 */
class YouTubePublishingService implements SocialPublishProvider
{
    protected const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    protected const CHANNELS_ENDPOINT = 'https://www.googleapis.com/youtube/v3/channels';

    protected const RESUMABLE_UPLOAD_ENDPOINT = 'https://www.googleapis.com/upload/youtube/v3/videos?uploadType=resumable&part=snippet,status';

    protected const SCOPE = 'https://www.googleapis.com/auth/youtube.upload';

    protected GoogleOauthSetting $settings;

    public function __construct()
    {
        $this->settings = GoogleOauthSetting::current();
    }

    public function key(): string
    {
        return 'youtube';
    }

    public function isAvailable(): bool
    {
        return $this->settings->youtubePublishingAvailable();
    }

    public function isApprovedForPublishing(): bool
    {
        return $this->settings->isYoutubeApprovedForPublishing();
    }

    protected function clientId(): string
    {
        return (string) $this->settings->credential('client_id');
    }

    protected function clientSecret(): string
    {
        return (string) $this->settings->credential('client_secret');
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
            'access_type' => 'offline',
            'prompt' => 'consent',
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $token = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            throw new RuntimeException('Google token exchange failed: '.$token->body());
        }

        if (! $token->json('refresh_token')) {
            throw new RuntimeException('Google did not grant offline access — please try connecting again and approve every permission requested.');
        }

        $channel = Http::withToken($token->json('access_token'))
            ->get(self::CHANNELS_ENDPOINT, ['part' => 'snippet', 'mine' => 'true']);

        $channelData = $channel->json('items.0');

        if ($channel->failed() || ! $channelData) {
            throw new RuntimeException('Could not find a YouTube channel on that Google account — create one first, then reconnect.');
        }

        return [
            'credentials' => [
                'access_token' => $token->json('access_token'),
                'refresh_token' => $token->json('refresh_token'),
                'expires_at' => now()->addSeconds((int) $token->json('expires_in', 3600))->toIso8601String(),
            ],
            'account_name' => (string) ($channelData['snippet']['title'] ?? 'YouTube channel'),
            'account_id' => (string) $channelData['id'],
        ];
    }

    protected function freshAccessToken(SocialConnection $connection): string
    {
        $expiresAt = $connection->credential('expires_at');

        if ($expiresAt && now()->lt($expiresAt) && $connection->credential('access_token')) {
            return (string) $connection->credential('access_token');
        }

        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
            'refresh_token' => $connection->credential('refresh_token'),
            'grant_type' => 'refresh_token',
        ]);

        if ($response->failed() || ! $response->json('access_token')) {
            throw new RuntimeException('Could not refresh the YouTube connection — it may have been revoked. Please reconnect YouTube.');
        }

        $accessToken = (string) $response->json('access_token');

        $connection->update([
            'credentials' => [
                ...$connection->credentials,
                'access_token' => $accessToken,
                'expires_at' => now()->addSeconds((int) $response->json('expires_in', 3600))->toIso8601String(),
            ],
        ]);

        return $accessToken;
    }

    public function publish(SocialConnection $connection, Generation $generation, string $title, string $caption): array
    {
        if (! $this->isApprovedForPublishing()) {
            throw new RuntimeException("YouTube hasn't approved this app for uploads yet — download the video and post it manually.");
        }

        if (! $generation->output) {
            throw new RuntimeException('This video has no file to publish yet.');
        }

        $accessToken = $this->freshAccessToken($connection);

        // Resumable upload, two steps: (1) initiate with metadata, get an
        // upload URL back in the Location header; (2) PUT the video bytes
        // to it. Simpler and more reliable over Http than hand-building a
        // multipart/related request for uploadType=multipart.
        $initiate = Http::withToken($accessToken)
            ->withHeaders(['X-Upload-Content-Type' => 'video/mp4'])
            ->post(self::RESUMABLE_UPLOAD_ENDPOINT, [
                'snippet' => ['title' => $title, 'description' => $caption, 'categoryId' => '22'],
                'status' => ['privacyStatus' => 'public'],
            ]);

        $uploadUrl = $initiate->header('Location');

        if ($initiate->failed() || ! $uploadUrl) {
            throw new RuntimeException('YouTube rejected the upload: '.$initiate->body());
        }

        $video = Http::get($generation->output);

        if ($video->failed()) {
            throw new RuntimeException('Could not fetch the rendered video to upload.');
        }

        $upload = Http::withBody($video->body(), 'video/mp4')->put($uploadUrl);

        if ($upload->failed() || ! $upload->json('id')) {
            throw new RuntimeException('YouTube upload failed: '.$upload->body());
        }

        $videoId = (string) $upload->json('id');

        return ['url' => "https://www.youtube.com/watch?v={$videoId}", 'external_id' => $videoId];
    }
}
