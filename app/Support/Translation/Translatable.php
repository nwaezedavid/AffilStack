<?php

namespace App\Support\Translation;

use Illuminate\Contracts\Support\Htmlable;
use Stringable;

/**
 * Text that should be translated even though it reaches the page as a
 * value (see Mark::text()).
 */
final class Translatable implements Htmlable, Stringable
{
    public function __construct(public readonly string $text) {}

    public function toHtml(): string
    {
        return e($this->text);
    }

    public function __toString(): string
    {
        return $this->text;
    }
}
