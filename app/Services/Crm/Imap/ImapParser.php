<?php

namespace App\Services\Crm\Imap;

/**
 * Turns one IMAP response (text with its {n} literals already read and
 * swapped for "\0index\0" markers by ImapClient) into nested PHP values:
 * a parenthesised list becomes an array, NIL becomes null, and atoms,
 * quoted strings and literals become strings. Atoms keep any [section] and
 * <origin> suffix intact, so "BODY[HEADER.FIELDS (FROM)]" stays one token.
 */
class ImapParser
{
    protected string $text;

    /** @var array<int, string> */
    protected array $literals;

    protected int $pos = 0;

    protected int $length;

    /**
     * @param  array<int, string>  $literals
     * @return array<int, mixed>
     */
    public static function parse(string $text, array $literals = []): array
    {
        $parser = new self;
        $parser->text = $text;
        $parser->literals = $literals;
        $parser->length = strlen($text);

        return $parser->parseList(false);
    }

    /**
     * @return array<int, mixed>
     */
    protected function parseList(bool $nested): array
    {
        $items = [];

        while ($this->pos < $this->length) {
            $char = $this->text[$this->pos];

            if ($char === ' ' || $char === "\r" || $char === "\n") {
                $this->pos++;

                continue;
            }

            if ($char === ')') {
                $this->pos++;

                if ($nested) {
                    return $items;
                }

                continue;
            }

            if ($char === '(') {
                $this->pos++;
                $items[] = $this->parseList(true);

                continue;
            }

            if ($char === '"') {
                $items[] = $this->parseQuoted();

                continue;
            }

            if ($char === "\0") {
                $end = strpos($this->text, "\0", $this->pos + 1);
                $index = (int) substr($this->text, $this->pos + 1, $end - $this->pos - 1);
                $items[] = $this->literals[$index] ?? '';
                $this->pos = $end + 1;

                continue;
            }

            $atom = $this->parseAtom();
            $items[] = strtoupper($atom) === 'NIL' ? null : $atom;
        }

        return $items;
    }

    protected function parseQuoted(): string
    {
        $this->pos++;
        $value = '';

        while ($this->pos < $this->length) {
            $char = $this->text[$this->pos];

            if ($char === '\\' && $this->pos + 1 < $this->length) {
                $value .= $this->text[$this->pos + 1];
                $this->pos += 2;

                continue;
            }

            if ($char === '"') {
                $this->pos++;

                return $value;
            }

            $value .= $char;
            $this->pos++;
        }

        return $value;
    }

    protected function parseAtom(): string
    {
        $start = $this->pos;
        $depth = 0;

        while ($this->pos < $this->length) {
            $char = $this->text[$this->pos];

            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth = max(0, $depth - 1);
            } elseif ($depth === 0 && ($char === ' ' || $char === '(' || $char === ')' || $char === "\0")) {
                break;
            }

            $this->pos++;
        }

        return substr($this->text, $start, $this->pos - $start);
    }
}
