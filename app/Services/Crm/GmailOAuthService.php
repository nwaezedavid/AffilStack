<?php

namespace App\Services\Crm;

use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mime\Email;

/**
 * "Connect Gmail" for CRM nurture sending (task #1) — a second, separate
 * OAuth flow from App\Services\Auth\GoogleOAuthService (sign-in): same
 * Google OAuth client id/secret (GoogleOauthSetting), but requesting the
 * gmail.send scope with offline access so a refresh token comes back, and
 * actually sending mail through the Gmail API afterward. Raw Http calls
 * only, no google/apiclient dependency — same reasoning as the sign-in
 * service and the payment gateways (CLAUDE.md: no new dependencies without
 * approval).
 */
class GmailOAuthService
{
    protected const AUTHORIZATION_ENDPOINT = 'https://accounts.google.com/o/oauth2/v2/auth';

    protected const TOKEN_ENDPOINT = 'https://oauth2.googleapis.com/token';

    protected const USERINFO_ENDPOINT = 'https://www.googleapis.com/oauth2/v3/userinfo';

    protected const SEND_ENDPOINT = 'https://gmail.googleapis.com/gmail/v1/users/me/messages/send';

    protected const SCOPE = 'openid email https://www.googleapis.com/auth/gmail.send';

    protected GoogleOauthSetting $settings;

    public function __construct()
    {
        $this->settings = GoogleOauthSetting::current();
    }

    public function isAvailable(): bool
    {
        return $this->settings->gmailSendingAvailable();
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
            // Forces Google to hand back a refresh_token even if this
            // Google account previously granted the same scope — without
            // this, a user reconnecting after a revoke would silently get
            // no refresh_token and sending would break the moment their
            // access token expires.
            'prompt' => 'consent',
        ]);

        return self::AUTHORIZATION_ENDPOINT.'?'.$query;
    }

    /**
     * Exchanges an authorization code for tokens + the connected Gmail
     * address, and returns exactly what EmailConnection::credentials needs.
     *
     * @return array{access_token: string, refresh_token: string, expires_at: string, email: string}
     */
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

        $profile = Http::withToken($token->json('access_token'))->get(self::USERINFO_ENDPOINT);

        if ($profile->failed() || ! $profile->json('email')) {
            throw new RuntimeException('Could not read the connected Gmail address: '.$profile->body());
        }

        return [
            'access_token' => (string) $token->json('access_token'),
            'refresh_token' => (string) $token->json('refresh_token'),
            'expires_at' => now()->addSeconds((int) $token->json('expires_in', 3600))->toIso8601String(),
            'email' => (string) $profile->json('email'),
        ];
    }

    /**
     * Refreshes and persists a new access token onto $connection when the
     * current one is missing or expired, and returns the access token to
     * use right now. Google never re-issues a refresh_token on this call,
     * so the original one is kept as-is.
     */
    protected function freshAccessToken(EmailConnection $connection): string
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
            throw new RuntimeException('Could not refresh the Gmail connection — it may have been revoked. Please reconnect Gmail.');
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

    /**
     * Sends one email through the connected Gmail account's own address —
     * via the Gmail API's users.messages.send, not SMTP. $connection must
     * be provider === 'gmail'.
     */
    public function send(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody): void
    {
        $accessToken = $this->freshAccessToken($connection);

        $message = (new Email)
            ->from($connection->connected_email)
            ->to($toEmail)
            ->subject($subject)
            ->html($htmlBody);

        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');

        $response = Http::withToken($accessToken)->post(self::SEND_ENDPOINT, ['raw' => $raw]);

        if ($response->failed()) {
            throw new RuntimeException('Gmail send failed: '.$response->body());
        }
    }
}
