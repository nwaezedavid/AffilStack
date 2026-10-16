<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * One piece of English text and its translation into one language. The
 * text keeps its inline markup as numbered placeholders — <g1>…</g1> for
 * a link or bold text, <x1/> for an icon or line break, <v1/> for a value
 * that changes per visitor (never sent for translation) — so the
 * translation can move them where the grammar needs them.
 *
 * Status: pending (waiting for Claude), machine (translated by Claude),
 * reviewed (written or approved by an admin; never overwritten), failed
 * (Claude's answer didn't keep the placeholders; shown in English).
 */
#[Fillable(['locale', 'hash', 'source', 'translation', 'status', 'attempts', 'context', 'last_seen_at'])]
class Translation extends Model
{
    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'last_seen_at' => 'date',
        ];
    }

    public static function hashFor(string $source): string
    {
        return sha1($source);
    }

    public function isUsable(): bool
    {
        return in_array($this->status, ['machine', 'reviewed'], true) && filled($this->translation);
    }
}
