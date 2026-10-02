<?php

namespace App\Services\Crm\Imap;

use RuntimeException;

/**
 * Any IMAP failure — network, TLS, a NO/BAD response, a timeout. $kind lets
 * the UI explain it in plain words (see ConnectionErrorExplainer).
 */
class ImapException extends RuntimeException
{
    public function __construct(string $message, public string $kind = 'protocol', ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
