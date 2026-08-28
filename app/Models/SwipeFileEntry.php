<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature 9 (Phase 3 backlog, item 9): the swipe file library. Unlike every
 * other Phase 3 module, this is deliberately NOT an AI-generation feature —
 * a "swipe file" is a collection of hooks/subject lines/thumbnail styles
 * that have actually proven to work, and the AI has no way to verify that,
 * so admin-curated content is the honest version of this feature. Same
 * pattern as FaqItem (also admin-curated, also fed to a public page):
 * managed from Admin → Content → Swipe Files, seeded with a real starter
 * set so it isn't empty on a fresh install.
 */
#[Fillable(['type', 'niche', 'title', 'content', 'notes', 'tags', 'is_published', 'sort_order'])]
class SwipeFileEntry extends Model
{
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_published' => 'boolean',
        ];
    }
}
