<?php

namespace App\Services\AI;

use Illuminate\Support\Facades\Log;
use RuntimeException;

class AIGenerationException extends RuntimeException
{
    /** Shown to customers when the AI provider itself fails. */
    public const UNAVAILABLE = 'The AI service is temporarily unavailable. Please try again in a few minutes — you weren\'t charged for this attempt.';

    /** Shown to customers when the AI answered with something unusable. */
    public const BAD_ANSWER = 'The AI returned an answer we couldn\'t use. Please try again — you weren\'t charged for this attempt.';

    /**
     * Logs the provider's own error (it can name keys, quotas or billing
     * details) and returns an exception whose message is safe to show
     * customers, since generation errors are displayed in the dashboard.
     *
     * @param  array<string, mixed>  $context
     */
    public static function fromProvider(string $what, array $context = [], string $message = self::UNAVAILABLE, ?\Throwable $previous = null): self
    {
        Log::warning($what, $context);

        return new self($message, previous: $previous);
    }
}
