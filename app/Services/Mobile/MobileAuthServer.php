<?php

namespace App\Services\Mobile;

use App\Models\ApiToken;
use App\Models\MobileAuthorizationCode;
use App\Models\MobileDevice;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sign-in for the official AffilStack phone apps: OAuth 2.0 authorization
 * code + PKCE (RFC 7636) with rotating refresh tokens, on the same
 * /api/oauth/token endpoint connected apps use.
 *
 * The app opens app.affilstack.com/mobile/authorize in the phone's secure
 * browser, so the customer signs in exactly as on the website — password
 * plus two-factor, or Google — and every web rule (suspension, team seats,
 * partner-only accounts) applies before a code is ever issued. The app is
 * a public client: it can't keep a secret, so PKCE (S256 only) and an
 * allow-listed redirect address are mandatory.
 *
 * Each sign-in creates a MobileDevice. Refresh tokens rotate on every use
 * and the previous one is remembered: if an old refresh token comes back,
 * someone copied it, so the whole device is signed out.
 */
class MobileAuthServer
{
    public function isMobileClient(string $clientId): bool
    {
        return $clientId !== '' && hash_equals((string) config('mobile.client_id'), $clientId);
    }

    /**
     * Checks a sign-in request from the app. "error" is shown on the page
     * (never redirect to an address that isn't registered);
     * "redirect_error" is sent back to the app.
     *
     * @param  array<string, string>  $params
     * @return array{error?: string, redirect_error?: string}
     */
    public function validateAuthorization(array $params): array
    {
        if (! $this->isMobileClient((string) ($params['client_id'] ?? ''))) {
            return ['error' => 'This sign-in link is not from the AffilStack app.'];
        }

        if (! in_array((string) ($params['redirect_uri'] ?? ''), (array) config('mobile.redirect_uris'), true)) {
            return ['error' => 'This sign-in link points to an address AffilStack does not recognise, so we stopped here to keep your account safe.'];
        }

        if (($params['response_type'] ?? '') !== 'code') {
            return ['redirect_error' => 'unsupported_response_type'];
        }

        $challenge = (string) ($params['code_challenge'] ?? '');

        if (($params['code_challenge_method'] ?? '') !== 'S256' || ! preg_match('/^[A-Za-z0-9\-_]{43,128}$/', $challenge)) {
            return ['redirect_error' => 'invalid_request'];
        }

        return [];
    }

    /**
     * Why $user may not use the app, or null when they may. Mirrors the
     * dashboard: suspended accounts (or seats of a suspended owner) and
     * partner-only accounts are kept out.
     */
    public function denialReason(?User $user): ?string
    {
        if (! $user || $user->trashed()) {
            return 'This account no longer exists.';
        }

        if ($user->is_suspended || $user->billableUser()->is_suspended) {
            return 'This account is suspended. Contact support for help.';
        }

        if ($user->isAffiliateOnly()) {
            return 'The AffilStack app is for AffilStack customers. Partners can use the Partner Portal on the website.';
        }

        return null;
    }

    public function issueCode(User $user, string $redirectUri, string $codeChallenge): string
    {
        $code = Str::random(64);

        MobileAuthorizationCode::create([
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'redirect_uri' => $redirectUri,
            'code_challenge' => $codeChallenge,
            'expires_at' => now()->addMinutes((int) config('mobile.authorization_code_minutes')),
        ]);

        return $code;
    }

    /**
     * /api/oauth/token for the app.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body
     */
    public function token(array $input): array
    {
        return match ($input['grant_type'] ?? null) {
            'authorization_code' => $this->exchangeCode($input),
            'refresh_token' => $this->refresh((string) ($input['refresh_token'] ?? '')),
            default => [400, ['error' => 'unsupported_grant_type']],
        };
    }

    /**
     * Signs out the device an access or refresh token belongs to. Always
     * "succeeds" (RFC 7009): an unknown token is already useless.
     */
    public function revoke(string $token): void
    {
        if ($token === '') {
            return;
        }

        $hash = hash('sha256', $token);

        $device = ApiToken::query()->where('token_hash', $hash)->where('type', 'mobile')->first()?->mobileDevice
            ?? MobileDevice::query()->where('refresh_token_hash', $hash)->first();

        $device?->revoke();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function exchangeCode(array $input): array
    {
        $code = MobileAuthorizationCode::query()->where('code_hash', hash('sha256', (string) ($input['code'] ?? '')))->first();

        if (! $code || $code->expires_at->isPast() || ! hash_equals($code->redirect_uri, (string) ($input['redirect_uri'] ?? ''))) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The code is invalid, expired, or was issued for another redirect URL.']];
        }

        // A code used twice means someone else may have it.
        if ($code->used_at !== null) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'This code has already been used.']];
        }

        $verifier = (string) ($input['code_verifier'] ?? '');
        $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        if ($verifier === '' || ! hash_equals($code->code_challenge, $expected)) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The code verifier does not match.']];
        }

        if (MobileAuthorizationCode::whereKey($code->id)->whereNull('used_at')->update(['used_at' => now()]) !== 1) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'This code has already been used.']];
        }

        $user = User::query()->find($code->user_id);

        if ($reason = $this->denialReason($user)) {
            return [400, ['error' => 'invalid_grant', 'error_description' => $reason]];
        }

        $platform = strtolower((string) ($input['platform'] ?? 'unknown'));

        $device = MobileDevice::create([
            'user_id' => $user->id,
            'name' => mb_substr(trim((string) ($input['device_name'] ?? '')), 0, 120) ?: null,
            'platform' => in_array($platform, MobileDevice::PLATFORMS, true) ? $platform : 'unknown',
            'app_version' => mb_substr((string) ($input['app_version'] ?? ''), 0, 32) ?: null,
            'authenticated_at' => now(),
            'last_seen_at' => now(),
        ]);

        return [200, $this->issue($device)];
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function refresh(string $plain): array
    {
        $invalid = [400, ['error' => 'invalid_grant', 'error_description' => 'The refresh token is invalid, expired or revoked.']];

        if ($plain === '') {
            return $invalid;
        }

        $hash = hash('sha256', $plain);
        $device = MobileDevice::query()->where('refresh_token_hash', $hash)->first();

        if (! $device) {
            // An already-rotated token coming back means two copies exist:
            // sign the device out so neither keeps working.
            MobileDevice::query()->where('previous_refresh_token_hash', $hash)->first()?->revoke();

            return $invalid;
        }

        if (! $device->isActive() || $device->refresh_expires_at === null || $device->refresh_expires_at->isPast()) {
            return $invalid;
        }

        if ($reason = $this->denialReason(User::query()->find($device->user_id))) {
            $device->revoke();

            return [400, ['error' => 'invalid_grant', 'error_description' => $reason]];
        }

        // Claim the token atomically so two simultaneous refreshes can't
        // both succeed.
        if (MobileDevice::whereKey($device->id)->where('refresh_token_hash', $hash)->update(['refresh_token_hash' => null, 'previous_refresh_token_hash' => hash('sha256', $plain)]) !== 1) {
            return $invalid;
        }

        return [200, $this->issue($device->refresh())];
    }

    /**
     * @return array<string, mixed>
     */
    protected function issue(MobileDevice $device): array
    {
        return DB::transaction(function () use ($device) {
            $device->apiTokens()->delete();

            $access = 'afmt_'.Str::random(48);
            $refresh = 'afmr_'.Str::random(64);
            $minutes = (int) config('mobile.access_token_minutes');

            ApiToken::create([
                'user_id' => $device->user_id,
                'mobile_device_id' => $device->id,
                'type' => 'mobile',
                'scope' => 'full',
                'is_sandbox' => false,
                'name' => 'AffilStack app'.($device->name ? ': '.$device->name : ''),
                'token_hash' => hash('sha256', $access),
                'expires_at' => now()->addMinutes($minutes),
            ]);

            $device->forceFill([
                'refresh_token_hash' => hash('sha256', $refresh),
                'refresh_expires_at' => now()->addDays((int) config('mobile.refresh_token_days')),
                'last_seen_at' => now(),
            ])->save();

            return [
                'access_token' => $access,
                'token_type' => 'Bearer',
                'expires_in' => $minutes * 60,
                'refresh_token' => $refresh,
                'device_id' => $device->id,
            ];
        });
    }
}
