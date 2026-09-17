<?php

namespace App\Services\Social;

use App\Models\Generation;
use App\Models\InstagramSetting;
use App\Models\SocialConnection;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Connect Instagram" + real publishing (task #2) via the Meta Graph API
 * (Facebook Login for Business — a connected Facebook Page's linked
 * Instagram professional account, there is no direct "log in with
 * Instagram" for business publishing). Gated by
 * InstagramSetting::isApprovedForPublishing() — Meta requires App Review
 * for the instagram_content_publish permission plus a verified Business
 * Manager before this works for real users; until approved, publish()
 * refuses outright and Dashboard\SocialPublishController falls back to
 * "download and post manually".
 *
 * Publishing uploads as a Reel (the only format that suits a short
 * vertical UGC-style video) via the two-step container flow: create a
 * media container with a video_url Instagram fetches itself (Generation::
 * output, already public — see UgcVideoService), wait for it to finish
 * processing, then publish the container.
 */
class InstagramPublishingService implements SocialPublishProvider
{
    protected const AUTHORIZATION_ENDPOINT = 'https://www.facebook.com/v19.0/dialog/oauth';

    protected const TOKEN_ENDPOINT = 'https://graph.facebook.com/v19.0/oauth/access_token';

    protected const GRAPH_BASE = 'https://graph.facebook.com/v19.0';

    protected const SCOPE = 'pages_show_list,instagram_basic,instagram_content_publish,pages_read_engagement';

    protected const CONTAINER_MAX_WAIT_SECONDS = 60;

    protected const CONTAINER_POLL_INTERVAL_SECONDS = 5;

    protected InstagramSetting $settings;

    public function __construct()
    {
        $this->settings = InstagramSetting::current();
    }

    public function key(): string
    {
        return 'instagram';
    }

    public function isAvailable(): bool
    {
        return $this->settings->isAvailable();
    }

    public function isApprovedForPublishing(): bool
    {
        return $this->settings->isApprovedForPublishing();
    }

    protected function appId(): string
    {
        return (string) $this->settings->credential('app_id');
    }

    protected function appSecret(): string
    {
        return (string) $this->settings->credential('app_secret');
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->appId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => self::SCOPE,
            'state' => $state,
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $token = Http::get(self::TOKEN_ENDPOINT, [
            'client_id' => $this->appId(),
            'client_secret' => $this->appSecret(),
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            throw new RuntimeException('Facebook token exchange failed: '.$token->body());
        }

        $userAccessToken = (string) $token->json('access_token');

        // Find the first connected Page that has a linked Instagram
        // professional account — that account, not the Facebook user
        // itself, is what actually publishes.
        $pages = Http::withToken($userAccessToken)
            ->get(self::GRAPH_BASE.'/me/accounts', ['fields' => 'name,access_token,instagram_business_account']);

        if ($pages->failed()) {
            throw new RuntimeException('Could not list connected Facebook Pages: '.$pages->body());
        }

        $pageWithInstagram = collect($pages->json('data', []))->first(fn ($page) => ! empty($page['instagram_business_account']['id']));

        if (! $pageWithInstagram) {
            throw new RuntimeException('No connected Facebook Page has a linked Instagram professional account — link one in Meta Business Suite, then reconnect.');
        }

        $igUserId = (string) $pageWithInstagram['instagram_business_account']['id'];

        $profile = Http::withToken($userAccessToken)
            ->get(self::GRAPH_BASE."/{$igUserId}", ['fields' => 'username']);

        return [
            'credentials' => [
                // The PAGE access token (not the user token) is what
                // authorizes publishing to that Page's Instagram account.
                'page_access_token' => $pageWithInstagram['access_token'],
                'ig_user_id' => $igUserId,
            ],
            'account_name' => '@'.(string) ($profile->json('username') ?: $igUserId),
            'account_id' => $igUserId,
        ];
    }

    public function publish(SocialConnection $connection, Generation $generation, string $title, string $caption): array
    {
        if (! $this->isApprovedForPublishing()) {
            throw new RuntimeException("Instagram hasn't approved this app for publishing yet — download the video and post it manually.");
        }

        if (! $generation->output) {
            throw new RuntimeException('This video has no file to publish yet.');
        }

        $accessToken = (string) $connection->credential('page_access_token');
        $igUserId = (string) $connection->credential('ig_user_id');

        $create = Http::withToken($accessToken)->post(self::GRAPH_BASE."/{$igUserId}/media", [
            'media_type' => 'REELS',
            'video_url' => $generation->output,
            'caption' => $caption,
        ]);

        $containerId = $create->json('id');

        if ($create->failed() || ! $containerId) {
            throw new RuntimeException('Instagram rejected the upload: '.$create->body());
        }

        if (! $this->waitUntilReady($accessToken, $containerId)) {
            throw new RuntimeException('Instagram is still processing this video — try publishing again in a minute.');
        }

        $publish = Http::withToken($accessToken)->post(self::GRAPH_BASE."/{$igUserId}/media_publish", [
            'creation_id' => $containerId,
        ]);

        $mediaId = $publish->json('id');

        if ($publish->failed() || ! $mediaId) {
            throw new RuntimeException('Instagram rejected publishing the processed video: '.$publish->body());
        }

        $permalink = Http::withToken($accessToken)->get(self::GRAPH_BASE."/{$mediaId}", ['fields' => 'permalink']);

        return ['url' => $permalink->json('permalink'), 'external_id' => (string) $mediaId];
    }

    protected function waitUntilReady(string $accessToken, string $containerId): bool
    {
        $deadline = now()->addSeconds(self::CONTAINER_MAX_WAIT_SECONDS);

        while (now()->lessThan($deadline)) {
            $status = Http::withToken($accessToken)
                ->get(self::GRAPH_BASE."/{$containerId}", ['fields' => 'status_code'])
                ->json('status_code');

            if ($status === 'FINISHED') {
                return true;
            }

            if ($status === 'ERROR') {
                return false;
            }

            $this->wait(self::CONTAINER_POLL_INTERVAL_SECONDS);
        }

        return false;
    }

    /**
     * A real sleep in production; a no-op under the test runner — same
     * pattern as UgcVideoService::wait().
     */
    protected function wait(int $seconds): void
    {
        if (! app()->runningUnitTests()) {
            sleep($seconds);
        }
    }
}
