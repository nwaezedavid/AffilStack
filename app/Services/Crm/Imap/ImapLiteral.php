<?php

namespace App\Services\Crm\Imap;

/**
 * A command argument sent as an IMAP synchronising literal ({n} + raw
 * bytes) — used for passwords with non-ASCII characters.
 */
class ImapLiteral
{
    public function __construct(public string $value) {}
}
