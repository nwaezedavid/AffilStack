<?php

namespace App\Services\Crm\Imap;

use Illuminate\Support\Carbon;

/**
 * One message read from a mailbox (IMAP or the Gmail API), decoded to
 * UTF-8 — what reply matching works from.
 */
class InboundEmail
{
    /**
     * @param  array<int, string>  $references
     */
    public function __construct(
        public string $externalId,
        public ?string $messageId,
        public ?string $inReplyTo,
        public array $references,
        public string $fromEmail,
        public ?string $fromName,
        public ?string $subject,
        public ?Carbon $date,
        public string $textBody,
        public ?string $threadId = null,
    ) {}

    /**
     * Builds the header half from a parsed header map.
     *
     * @param  array<string, string>  $headers
     */
    public static function fromHeaders(string $externalId, array $headers, string $textBody, ?string $threadId = null): self
    {
        $from = MimeDecoder::parseAddress($headers['from'] ?? '');

        try {
            $date = isset($headers['date']) ? Carbon::parse(preg_replace('/\s*\([^)]*\)\s*$/', '', $headers['date'])) : null;
        } catch (\Throwable) {
            $date = null;
        }

        return new self(
            externalId: $externalId,
            messageId: MimeDecoder::messageIds($headers['message-id'] ?? null)[0] ?? null,
            inReplyTo: MimeDecoder::messageIds($headers['in-reply-to'] ?? null)[0] ?? null,
            references: MimeDecoder::messageIds($headers['references'] ?? null),
            fromEmail: $from['email'],
            fromName: $from['name'],
            subject: $headers['subject'] ?? null,
            date: $date,
            textBody: $textBody,
            threadId: $threadId,
        );
    }

    /**
     * Every Message-ID this email says it answers, most specific first.
     *
     * @return array<int, string>
     */
    public function referencedIds(): array
    {
        return array_values(array_unique(array_filter([$this->inReplyTo, ...array_reverse($this->references)])));
    }
}
