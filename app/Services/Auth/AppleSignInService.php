<?php

namespace App\Services\Auth;

use App\Models\AppleOauthSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * "Continue with Apple" via raw Http calls against Apple's own endpoints —
 * no new Composer dependency (CLAUDE.md), same approach as
 * GoogleOAuthService.
 *
 * Apple has no static client secret: each request signs a short-lived
 * ES256 JWT with the .p8 key from admin (Team ID as issuer, Services ID as
 * subject). The ID token comes straight back from Apple's token endpoint
 * over TLS, so — as OpenID Connect Core 3.1.3.7 allows for tokens received
 * directly from the token endpoint — its claims (issuer, audience, expiry,
 * nonce) are checked without fetching Apple's signing keys.
 */
class AppleSignInService
{
    public const ISSUER = 'https://appleid.apple.com';

    protected const AUTHORIZATION_ENDPOINT = 'https://appleid.apple.com/auth/authorize';

    protected const TOKEN_ENDPOINT = 'https://appleid.apple.com/auth/token';

    protected const REVOKE_ENDPOINT = 'https://appleid.apple.com/auth/revoke';

    protected AppleOauthSetting $settings;

    public function __construct()
    {
        $this->settings = AppleOauthSetting::current();
    }

    public function isEnabled(): bool
    {
        return $this->settings->is_enabled && $this->isConfigured();
    }

    public function isConfigured(): bool
    {
        return $this->servicesId() !== '' && $this->teamId() !== '' && $this->keyId() !== '' && $this->privateKey() !== '';
    }

    protected function servicesId(): string
    {
        return trim((string) $this->settings->credential('services_id'));
    }

    protected function teamId(): string
    {
        return trim((string) $this->settings->credential('team_id'));
    }

    protected function keyId(): string
    {
        return trim((string) $this->settings->credential('key_id'));
    }

    protected function privateKey(): string
    {
        return trim((string) $this->settings->credential('private_key'));
    }

    /**
     * Apple posts the result back (response_mode=form_post is required
     * when asking for the email address).
     */
    public function authorizationUrl(string $redirectUri, string $state, string $nonce): string
    {
        return self::AUTHORIZATION_ENDPOINT.'?'.http_build_query([
            'client_id' => $this->servicesId(),
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'response_mode' => 'form_post',
            'scope' => 'name email',
            'state' => $state,
            'nonce' => hash('sha256', $nonce),
        ]);
    }

    /**
     * Exchanges the authorization code for the person's Apple identity.
     *
     * @return array{sub: string, email: string, email_verified: bool, is_private_email: bool, refresh_token: string|null}
     */
    public function resolveProfile(string $code, string $redirectUri, string $nonce): array
    {
        $response = Http::asForm()->post(self::TOKEN_ENDPOINT, [
            'client_id' => $this->servicesId(),
            'client_secret' => $this->clientSecret(),
            'code' => $code,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $redirectUri,
        ]);

        if ($response->failed() || ! is_string($response->json('id_token'))) {
            throw new RuntimeException('Apple token exchange failed: '.$response->body());
        }

        $claims = $this->decodeIdToken((string) $response->json('id_token'));

        if (($claims['iss'] ?? null) !== self::ISSUER) {
            throw new RuntimeException('Apple ID token has the wrong issuer.');
        }

        $audience = (array) ($claims['aud'] ?? []);

        if (! in_array($this->servicesId(), $audience, true)) {
            throw new RuntimeException('Apple ID token was issued for another app.');
        }

        if ((int) ($claims['exp'] ?? 0) < time() - 60) {
            throw new RuntimeException('Apple ID token has expired.');
        }

        if (! isset($claims['nonce']) || ! hash_equals(hash('sha256', $nonce), (string) $claims['nonce'])) {
            throw new RuntimeException('Apple ID token nonce does not match this sign-in.');
        }

        if (empty($claims['sub'])) {
            throw new RuntimeException('Apple ID token has no subject.');
        }

        return [
            'sub' => (string) $claims['sub'],
            'email' => strtolower((string) ($claims['email'] ?? '')),
            'email_verified' => filter_var($claims['email_verified'] ?? false, FILTER_VALIDATE_BOOL),
            'is_private_email' => filter_var($claims['is_private_email'] ?? false, FILTER_VALIDATE_BOOL),
            'refresh_token' => is_string($response->json('refresh_token')) ? $response->json('refresh_token') : null,
        ];
    }

    /**
     * Tells Apple to unlink AffilStack from the person's Apple ID — Apple
     * requires this when an account that signed in with Apple is deleted.
     * Best effort: a failure is reported, never blocks the deletion.
     */
    public function revoke(string $refreshToken): bool
    {
        if (! $this->isConfigured() || $refreshToken === '') {
            return false;
        }

        try {
            return Http::asForm()->post(self::REVOKE_ENDPOINT, [
                'client_id' => $this->servicesId(),
                'client_secret' => $this->clientSecret(),
                'token' => $refreshToken,
                'token_type_hint' => 'refresh_token',
            ])->successful();
        } catch (\Throwable $e) {
            report($e);

            return false;
        }
    }

    /**
     * The ES256-signed JWT Apple accepts as a client secret.
     */
    public function clientSecret(): string
    {
        $key = openssl_pkey_get_private($this->privateKey());

        if ($key === false) {
            throw new RuntimeException('The Apple private key could not be read.');
        }

        $header = $this->base64Url((string) json_encode(['alg' => 'ES256', 'kid' => $this->keyId()]));
        $payload = $this->base64Url((string) json_encode([
            'iss' => $this->teamId(),
            'iat' => time(),
            'exp' => time() + 300,
            'aud' => self::ISSUER,
            'sub' => $this->servicesId(),
        ]));

        if (! openssl_sign("{$header}.{$payload}", $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Signing the Apple client secret failed.');
        }

        return "{$header}.{$payload}.".$this->base64Url($this->derToJose($der));
    }

    /**
     * Format checks plus a real signature with the saved key — catches a
     * wrong or damaged .p8 file before anyone taps the button. Whether
     * Apple accepts the IDs together is only known on the first sign-in.
     *
     * @return array{success: bool, message: string}
     */
    public function verifyCredentials(): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'message' => 'Services ID, Team ID, Key ID and the private key are all required.'];
        }

        if (! preg_match('/^[A-Z0-9]{10}$/', $this->teamId())) {
            return ['success' => false, 'message' => 'The Team ID should be 10 letters and numbers (top right of your Apple Developer account).'];
        }

        if (! preg_match('/^[A-Z0-9]{10}$/', $this->keyId())) {
            return ['success' => false, 'message' => 'The Key ID should be 10 letters and numbers (shown next to the key you created).'];
        }

        if (! preg_match('/^[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)+$/', $this->servicesId())) {
            return ['success' => false, 'message' => 'The Services ID should look like com.affilstack.signin.'];
        }

        $key = openssl_pkey_get_private($this->privateKey());
        $details = $key ? openssl_pkey_get_details($key) : false;

        if (! $details || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_EC) {
            return ['success' => false, 'message' => 'That private key could not be read. Paste the whole .p8 file, including the BEGIN and END lines.'];
        }

        try {
            $this->clientSecret();
        } catch (RuntimeException) {
            return ['success' => false, 'message' => 'The private key could not sign a test request.'];
        }

        return ['success' => true, 'message' => 'The keys are correctly formatted and the private key works. Full verification happens the first time someone uses "Continue with Apple".'];
    }

    /**
     * @return array<string, mixed>
     */
    protected function decodeIdToken(string $jwt): array
    {
        $parts = explode('.', $jwt);

        if (count($parts) !== 3) {
            throw new RuntimeException('Apple ID token is malformed.');
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        if (! is_array($claims)) {
            throw new RuntimeException('Apple ID token is malformed.');
        }

        return $claims;
    }

    protected function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    /**
     * OpenSSL returns an ECDSA signature as DER (SEQUENCE of two INTEGERs);
     * JWT wants the two 32-byte numbers side by side.
     */
    protected function derToJose(string $der): string
    {
        $offset = 2;

        if ((ord($der[1]) & 0x80) !== 0) {
            $offset += ord($der[1]) & 0x7F;
        }

        $parts = '';

        for ($i = 0; $i < 2; $i++) {
            $length = ord($der[$offset + 1]);
            $integer = substr($der, $offset + 2, $length);
            $parts .= str_pad(ltrim($integer, "\x00"), 32, "\x00", STR_PAD_LEFT);
            $offset += 2 + $length;
        }

        return $parts;
    }
}
