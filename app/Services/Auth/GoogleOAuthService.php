<?php

namespace App\Services\Auth;

use App\Models\GoogleOauthSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Continue with Google" via raw Http calls against Google's own OAuth 2.0
 * endpoints — no laravel/socialite dependency, same reasoning as the
 * payment gateways: avoid a new Composer dependency per CLAUDE.md when a
 * plain REST call does the job.
 */
class GoogleOAuthService
{
    protected const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected GoogleOauthSetting $settings;

    public function __construct()
    {
        $this->settings = GoogleOauthSetting::current();
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled && $this->clientId() !== '' && $this->clientSecret() !== '';
    }

    protected function clientId(): string
    {
        return (string) ($this->settings->credential('client_id') ?: config('services.google_oauth.client_id'));
    }

    protected function clientSecret(): string
    {
        return (string) ($this->settings->credential('client_secret') ?: config('services.google_oauth.client_secret'));
    }

    public function authorizationUrl(string $redirectUri, string $state): string
    {
        $query = http_build_query([
            'client_id' => $this->clientId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * Exchanges an authorization code for the caller's verified profile.
     *
     * @return array{sub: string, email: string, email_verified: bool, name: string}
     */
    public function resolveProfile(string $code, string $redirectUri): array
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

        $profile = Http::withToken($token->json('access_token'))->get(self::USERINFO_ENDPOINT);

        if ($profile->failed() || ! $profile->json('sub')) {
            throw new RuntimeException('Google profile lookup failed: '.$profile->body());
        }

        return [
            'sub' => (string) $profile->json('sub'),
            'email' => (string) $profile->json('email'),
            'email_verified' => (bool) $profile->json('email_verified'),
            'name' => (string) ($profile->json('name') ?: $profile->json('email')),
        ];
    }

    /**
     * There's no "ping" endpoint that meaningfully tests a client
     * id/secret pair without completing a real OAuth round trip — Google's
     * token endpoint only responds to an actual authorization code. This is
     * a format sanity check, not a live credential verification: it catches
     * the most common mistakes (blank fields, pasting the wrong value) but
     * a genuinely wrong secret paired with a correctly-shaped one still
     * only surfaces the first time someone clicks "Continue with Google".
     *
     * @return array{success: bool, message: string}
     */
    public function verifyCredentials(): array
    {
        $clientId = $this->clientId();
        $clientSecret = $this->clientSecret();

        if ($clientId === '' || $clientSecret === '') {
            return ['success' => false, 'message' => 'Client ID and client secret are both required.'];
        }

        if (! str_ends_with($clientId, '.apps.googleusercontent.com')) {
            return ['success' => false, 'message' => 'That doesn\'t look like a Google OAuth client ID — it should end with ".apps.googleusercontent.com".'];
        }

        if (strlen($clientSecret) < 8) {
            return ['success' => false, 'message' => 'That client secret looks too short to be valid.'];
        }

        return ['success' => true, 'message' => 'Client ID and secret are correctly formatted. Full verification happens the first time someone uses "Continue with Google".'];
    }
}
