<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'user_id', 'name', 'company', 'title', 'email', 'phone', 'website',
    'source', 'location', 'notes', 'status', 'raw_data',
])]
class CrmContact extends Model
{
    protected function casts(): array
    {
        return [
            'raw_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
