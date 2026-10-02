<?php

namespace App\Services\Social;

use RuntimeException;

/**
 * Thrown when a provider rejects a stored token or refresh token outright
 * (revoked, expired, permissions withdrawn) — reconnecting is the only fix.
 * Still a RuntimeException, so publish() callers that only catch that keep
 * working unchanged.
 */
class ConnectionExpiredException extends RuntimeException {}
