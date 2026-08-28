<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'offer_id', 'module', 'input', 'output', 'output_meta',
    'credits_spent', 'status', 'error_message',
])]
class Generation extends Model
{
    protected function casts(): array
    {
        return [
            'input' => 'array',
            'output_meta' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
