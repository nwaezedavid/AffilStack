<?php

namespace App\Services\Social;

use App\Models\SocialConnection;

/**
 * A provider that can prove a stored connection still works by calling one
 * cheap authenticated endpoint (refreshing the access token first where
 * the platform supports it). Implementations must not throw for an
 * ordinary API failure — they classify it into a ConnectionCheck instead:
 * "expired" when the user must reconnect (revoked/invalid token, refresh
 * rejected, missing permission), "failed" for anything that might clear up
 * on its own (rate limits, provider outages). Network-level failures
 * (ConnectionException) are handled by SocialConnectionVerifier.
 */
interface VerifiesConnection
{
    public function verify(SocialConnection $connection): ConnectionCheck;
}
