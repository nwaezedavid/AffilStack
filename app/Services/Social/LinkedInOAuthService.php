<?php

namespace App\Services\Social;

use App\Models\LinkedInOauthSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Connect LinkedIn" (task #3) — deliberately identity-only: "Sign In with
 * LinkedIn using OpenID Connect" (scope: openid profile email), a product
 * any LinkedIn developer app can self-serve enable with no partner review.
 * This NEVER requests a posting scope (w_member_social etc.) — per the
 * clarified backlog, LinkedIn content is exported for the user to publish
 * manually, never auto-posted, so there is no reason to ask for write
 * access to someone's LinkedIn account at all. Connecting exists purely so
 * generated content is exported in the context of a real, named LinkedIn
 * identity rather than an anonymous download.
 */
class LinkedInOAuthService implements SocialOAuthProvider
{
    protected const AUTHORIZATION_ENDPOINT = 'https://www.linkedin.com/oauth/v2/authorization';

    protected const TOKEN_ENDPOINT = 'https://www.linkedin.com/oauth/v2/accessToken';

    protected const USERINFO_ENDPOINT = 'https://api.linkedin.com/v2/userinfo';

    protected LinkedInOauthSetting $settings;

    public function __construct()
    {
        $this->settings = LinkedInOauthSetting::current();
    }

    public function key(): string
    {
        return 'linkedin';
    }

    public function isAvailable(): bool
    {
        return $this->settings->isAvailable();
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
            'response_type' => 'code',
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'state' => $state,
            'scope' => 'openid profile email',
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    public function exchangeCode(string $code, string $redirectUri): array
    {
        $token = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $this->clientId(),
            'client_secret' => $this->clientSecret(),
        ]);

        if ($token->failed() || ! $token->json('access_token')) {
            throw new RuntimeException('LinkedIn token exchange failed: '.$token->body());
        }

        $profile = Http::withToken($token->json('access_token'))->get(self::USERINFO_ENDPOINT);

        if ($profile->failed() || ! $profile->json('sub')) {
            throw new RuntimeException('Could not read the connected LinkedIn profile: '.$profile->body());
        }

        return [
            'credentials' => [
                'access_token' => $token->json('access_token'),
            ],
            'account_name' => (string) ($profile->json('name') ?: $profile->json('email') ?: 'LinkedIn member'),
            'account_id' => (string) $profile->json('sub'),
        ];
    }
}
