<?php

namespace App\Rules;

use App\Support\OutboundUrlGuard;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A URL the server may call: http(s), no credentials, and resolving only to
 * public internet addresses — see OutboundUrlGuard.
 */
class PublicUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $problem = OutboundUrlGuard::problem((string) $value);

        if ($problem !== null) {
            $fail($problem);
        }
    }
}
