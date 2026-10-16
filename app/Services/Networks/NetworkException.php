<?php

namespace App\Services\Networks;

use RuntimeException;

/**
 * A network refused or failed a request. The message is written for the
 * customer: what went wrong and what to do about it.
 */
class NetworkException extends RuntimeException
{
    /**
     * The credentials themselves were refused (revoked, wrong, missing
     * permission), as opposed to a temporary outage.
     */
    public bool $credentialsRejected = false;

    public static function rejected(string $message): self
    {
        $exception = new self($message);
        $exception->credentialsRejected = true;

        return $exception;
    }
}
