<?php

namespace App\Services\Crm;

use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Symfony\Component\Mime\Address;
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

    /**
     * Added to SCOPE only while the admin's "Allow Gmail inbox reading"
     * toggle is on (Google CASA approval) — a restricted scope.
     */
    public const READ_SCOPE = 'https://www.googleapis.com/auth/gmail.readonly';

    protected GoogleOauthSetting $settings;

    public function __construct()
    {
        $this->settings = GoogleOauthSetting::current();
    }

    public function isAvailable(): bool
    {
        return $this->settings->gmailSendingAvailable();
    }

    /**
     * Best-effort revocation on disconnect — never blocks the disconnect
     * itself (the token may already be revoked or expired).
     */
    public function revoke(string $token): void
    {
        if ($token === '') {
            return;
        }

        try {
            Http::asForm()->timeout(10)->post('https://oauth2.googleapis.com/revoke', ['token' => $token]);
        } catch (\Throwable) {
            // Revocation is advisory; the local copy is deleted regardless.
        }
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
            'scope' => $this->settings->gmailReadingAvailable() ? self::SCOPE.' '.self::READ_SCOPE : self::SCOPE,
            'include_granted_scopes' => 'true',
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
     * @return array{access_token: string, refresh_token: string, expires_at: string, email: string, read_granted: bool}
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
            'read_granted' => in_array(self::READ_SCOPE, explode(' ', (string) $token->json('scope')), true),
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
    /**
     * @param  array<string, mixed>  $options  see PersonalEmailSender::send()
     * @return array{provider_message_id: string|null, provider_thread_id: string|null}
     */
    public function send(EmailConnection $connection, string $toEmail, string $subject, string $htmlBody, array $options = []): array
    {
        $accessToken = $this->freshAccessToken($connection);

        $fromName = $connection->credential('from_name');
        $message = (new Email)
            ->from($fromName ? new Address((string) $connection->connected_email, (string) $fromName) : (string) $connection->connected_email)
            ->to($toEmail)
            ->subject($subject)
            ->html($htmlBody);

        PersonalEmailSender::applyOptions($message, $options);

        $raw = rtrim(strtr(base64_encode($message->toString()), '+/', '-_'), '=');
        $payload = ['raw' => $raw];

        if (! empty($options['thread_id'])) {
            $payload['threadId'] = $options['thread_id'];
        }

        $response = Http::withToken($accessToken)->post(self::SEND_ENDPOINT, $payload);

        if ($response->failed()) {
            throw new RuntimeException('Gmail send failed: '.$response->body());
        }

        return [
            'provider_message_id' => $response->json('id'),
            'provider_thread_id' => $response->json('threadId'),
        ];
    }

    /**
     * A live check that the stored tokens still work: refreshes the access
     * token if needed and reads the account's address.
     *
     * @throws RuntimeException
     */
    public function verify(EmailConnection $connection): string
    {
        $accessToken = $this->freshAccessToken($connection);
        $profile = Http::withToken($accessToken)->timeout(15)->get(self::USERINFO_ENDPOINT);

        if ($profile->failed() || ! $profile->json('email')) {
            throw new RuntimeException('Google did not accept the stored Gmail permission — please reconnect Gmail.');
        }

        return (string) $profile->json('email');
    }

    /**
     * Public so the Gmail inbox reader can share token refreshing.
     */
    public function accessTokenFor(EmailConnection $connection): string
    {
        return $this->freshAccessToken($connection);
    }
}
