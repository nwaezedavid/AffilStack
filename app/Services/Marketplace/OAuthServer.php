<?php

namespace App\Services\Marketplace;

use App\Models\Addon;
use App\Models\AddonInstall;
use App\Models\ApiToken;
use App\Models\OAuthAuthorizationCode;
use App\Models\OAuthRefreshToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * "Connect with AffilStack": the OAuth 2.0 authorization-code flow (with
 * optional PKCE) that lets a connected app act for a customer, limited to
 * the permissions the customer allowed.
 */
class OAuthServer
{
    public function __construct(protected AddonInstaller $installer) {}

    /**
     * Checks an authorization request. Returns the add-on and the
     * permissions asked for, or an error message to show the customer
     * (never redirect to an address that isn't registered).
     *
     * @param  array<string, mixed>  $query
     * @return array{addon?: Addon, scopes?: array<int, string>, error?: string, redirect_error?: string}
     */
    public function validateAuthorization(array $query, ?User $user): array
    {
        $addon = Addon::query()->where('type', 'app')->where('client_id', (string) ($query['client_id'] ?? ''))->first();

        if (! $addon) {
            return ['error' => 'This app is not registered with AffilStack.'];
        }

        $version = $addon->versionFor($user);

        if (! $version || (! $addon->isListed() && $addon->developer?->user_id !== $user?->id)) {
            return ['error' => 'This app is not available on AffilStack right now.'];
        }

        if (! in_array((string) ($query['redirect_uri'] ?? ''), $version->redirect_uris ?? [], true)) {
            return ['error' => 'This app sent you to an address it has not registered, so AffilStack stopped here to keep your account safe.'];
        }

        if (($query['response_type'] ?? '') !== 'code') {
            return ['addon' => $addon, 'redirect_error' => 'unsupported_response_type'];
        }

        if (isset($query['code_challenge']) && ($query['code_challenge_method'] ?? 'plain') !== 'S256') {
            return ['addon' => $addon, 'redirect_error' => 'invalid_request'];
        }

        $requested = array_values(array_filter(preg_split('/[\s,]+/', (string) ($query['scope'] ?? '')) ?: []));
        $scopes = $requested === [] ? ($version->scopes ?? []) : array_values(array_intersect($requested, $version->scopes ?? []));

        if ($scopes === [] || count($scopes) !== count($requested === [] ? $scopes : $requested)) {
            return ['addon' => $addon, 'redirect_error' => 'invalid_scope'];
        }

        return ['addon' => $addon, 'scopes' => $scopes];
    }

    /**
     * The customer clicked "Allow": install the app and hand it a code.
     *
     * @param  array<int, string>  $scopes
     */
    public function approve(User $user, Addon $addon, array $scopes, string $redirectUri, ?string $codeChallenge): string
    {
        $install = $this->installer->install($user, $addon, $scopes);

        if (! $install->isUsable()) {
            throw new \RuntimeException('Subscribe to this app before connecting it.');
        }

        $code = Str::random(64);

        OAuthAuthorizationCode::create([
            'addon_id' => $addon->id,
            'user_id' => $user->id,
            'code_hash' => hash('sha256', $code),
            'redirect_uri' => $redirectUri,
            'scopes' => $install->scopes ?? [],
            'code_challenge' => $codeChallenge,
            'expires_at' => now()->addMinutes((int) config('marketplace.authorization_code_minutes')),
        ]);

        return $code;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: array<string, mixed>} HTTP status and JSON body
     */
    public function token(array $input): array
    {
        $addon = $this->client((string) ($input['client_id'] ?? ''), (string) ($input['client_secret'] ?? ''));

        if (! $addon) {
            return [401, ['error' => 'invalid_client', 'error_description' => 'Unknown client or wrong client secret.']];
        }

        return match ($input['grant_type'] ?? null) {
            'authorization_code' => $this->exchangeCode($addon, $input),
            'refresh_token' => $this->refresh($addon, (string) ($input['refresh_token'] ?? '')),
            default => [400, ['error' => 'unsupported_grant_type']],
        };
    }

    public function revoke(string $clientId, string $clientSecret, string $token): bool
    {
        $addon = $this->client($clientId, $clientSecret);

        if (! $addon) {
            return false;
        }

        $hash = hash('sha256', $token);

        ApiToken::query()->where('token_hash', $hash)->where('type', 'app')
            ->whereHas('install', fn ($query) => $query->where('addon_id', $addon->id))
            ->delete();

        OAuthRefreshToken::query()->where('token_hash', $hash)->whereNull('revoked_at')
            ->whereHas('install', fn ($query) => $query->where('addon_id', $addon->id))
            ->update(['revoked_at' => now()]);

        return true;
    }

    protected function client(string $clientId, string $clientSecret): ?Addon
    {
        if ($clientId === '' || $clientSecret === '') {
            return null;
        }

        $addon = Addon::query()->where('type', 'app')->where('client_id', $clientId)->first();

        return $addon && $addon->secretMatches($clientSecret) && in_array($addon->status, ['draft', 'published', 'retired'], true) && $addon->developer?->isActive() ? $addon : null;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function exchangeCode(Addon $addon, array $input): array
    {
        $code = OAuthAuthorizationCode::query()->where('code_hash', hash('sha256', (string) ($input['code'] ?? '')))->first();

        if (! $code || $code->addon_id !== $addon->id || $code->expires_at->isPast() || ! hash_equals($code->redirect_uri, (string) ($input['redirect_uri'] ?? ''))) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The code is invalid, expired, or was issued for another redirect URL.']];
        }

        $install = AddonInstall::query()->where('addon_id', $addon->id)->where('user_id', $code->user_id)->first();

        // A code used twice means someone else may have it: sign the app
        // out of that customer's account.
        if ($code->used_at !== null) {
            if ($install) {
                $this->installer->revokeAccess($install);
            }

            return [400, ['error' => 'invalid_grant', 'error_description' => 'This code has already been used.']];
        }

        if ($code->code_challenge !== null) {
            $verifier = (string) ($input['code_verifier'] ?? '');
            $expected = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

            if ($verifier === '' || ! hash_equals($code->code_challenge, $expected)) {
                return [400, ['error' => 'invalid_grant', 'error_description' => 'The code verifier does not match.']];
            }
        }

        if (OAuthAuthorizationCode::whereKey($code->id)->whereNull('used_at')->update(['used_at' => now()]) !== 1) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'This code has already been used.']];
        }

        if (! $install || ! $install->isUsable() || $install->user?->is_suspended) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The customer no longer has this app.']];
        }

        return [200, $this->issue($install, array_values(array_intersect($code->scopes ?? [], $install->scopes ?? [])))];
    }

    /**
     * @return array{0: int, 1: array<string, mixed>}
     */
    protected function refresh(Addon $addon, string $plain): array
    {
        $token = OAuthRefreshToken::query()->where('token_hash', hash('sha256', $plain))->first();
        $install = $token?->install;

        if (! $token || ! $install || $install->addon_id !== $addon->id || $token->revoked_at !== null || $token->expires_at->isPast()) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The refresh token is invalid, expired or revoked.']];
        }

        if (OAuthRefreshToken::whereKey($token->id)->whereNull('revoked_at')->update(['revoked_at' => now()]) !== 1) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The refresh token has already been used.']];
        }

        if (! $install->isUsable() || $install->user?->is_suspended) {
            return [400, ['error' => 'invalid_grant', 'error_description' => 'The customer no longer has this app.']];
        }

        return [200, $this->issue($install, $install->scopes ?? [])];
    }

    /**
     * @param  array<int, string>  $scopes
     * @return array<string, mixed>
     */
    protected function issue(AddonInstall $install, array $scopes): array
    {
        return DB::transaction(function () use ($install, $scopes) {
            $install->apiTokens()->where('expires_at', '<', now())->delete();

            $access = 'afat_'.Str::random(48);
            $refresh = 'afrt_'.Str::random(64);
            $minutes = (int) config('marketplace.access_token_minutes');

            ApiToken::create([
                'user_id' => $install->user_id,
                'addon_install_id' => $install->id,
                'type' => 'app',
                'scope' => in_array('offers:write', $scopes, true) || in_array('crm:write', $scopes, true) ? 'full' : 'read_only',
                'scopes' => $scopes,
                'is_sandbox' => false,
                'name' => 'App: '.($install->addon->versionFor($install->user)?->name ?? $install->addon->slug),
                'token_hash' => hash('sha256', $access),
                'expires_at' => now()->addMinutes($minutes),
            ]);

            $install->refreshTokens()->create([
                'token_hash' => hash('sha256', $refresh),
                'expires_at' => now()->addDays((int) config('marketplace.refresh_token_days')),
            ]);

            return [
                'access_token' => $access,
                'token_type' => 'Bearer',
                'expires_in' => $minutes * 60,
                'refresh_token' => $refresh,
                'scope' => implode(' ', $scopes),
            ];
        });
    }
}
