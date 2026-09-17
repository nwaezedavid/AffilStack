<?php

namespace App\Services\Social;

/**
 * A user-facing "connect your own account" OAuth flow for one social
 * platform — LinkedIn (task #3, identity-only) today; YouTube/TikTok/
 * Instagram (task #2) will each get their own implementation reusing the
 * same SocialConnection model and Dashboard\SocialConnectionController.
 */
interface SocialOAuthProvider
{
    /**
     * The provider key stored on SocialConnection::provider, e.g. 'linkedin'.
     */
    public function key(): string;

    /**
     * Whether an admin has configured (and enabled) this provider's OAuth
     * app credentials — see each provider's own settings model.
     */
    public function isAvailable(): bool;

    public function authorizationUrl(string $redirectUri, string $state): string;

    /**
     * Exchanges an authorization code for whatever SocialConnection needs
     * to persist a connection.
     *
     * @return array{credentials: array<string, mixed>, account_name: string, account_id: ?string}
     */
    public function exchangeCode(string $code, string $redirectUri): array;
}
