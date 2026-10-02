<?php

namespace App\Services\Social;

use App\Models\SocialConnection;

/**
 * The outcome of one live check of a SocialConnection against its
 * provider — see VerifiesConnection::verify(). Carries whatever fresh
 * profile details the check happened to return (display name, avatar) so
 * the Connected Accounts card stays current without a reconnect.
 */
final class ConnectionCheck
{
    public function __construct(
        public readonly string $status,
        public readonly string $message,
        public readonly ?string $accountName = null,
        public readonly ?string $avatarUrl = null,
    ) {}

    public static function verified(string $message, ?string $accountName = null, ?string $avatarUrl = null): self
    {
        return new self(SocialConnection::STATUS_VERIFIED, $message, $accountName, $avatarUrl);
    }

    public static function failed(string $message): self
    {
        return new self(SocialConnection::STATUS_FAILED, $message);
    }

    public static function expired(string $message): self
    {
        return new self(SocialConnection::STATUS_EXPIRED, $message);
    }

    public function isVerified(): bool
    {
        return $this->status === SocialConnection::STATUS_VERIFIED;
    }
}
