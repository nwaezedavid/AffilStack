<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A long-form static page (About, Terms, Privacy Policy, etc.) whose title
 * and body the admin can edit from Filament without a code deploy. Looked
 * up by slug — see PageController.
 */
#[Fillable(['slug', 'title', 'meta_description', 'content', 'is_published'])]
class SitePage extends Model
{
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
        ];
    }
}
