<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A template in a customer's library, installed from a template pack.
 */
#[Fillable(['user_id', 'addon_install_id', 'kind', 'title', 'body', 'meta'])]
class UserTemplate extends Model
{
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function install(): BelongsTo
    {
        return $this->belongsTo(AddonInstall::class, 'addon_install_id');
    }
}
