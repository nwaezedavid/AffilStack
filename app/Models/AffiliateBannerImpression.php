<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A daily impression counter for one affiliate's embedded banner. See
 * BannerEmbedController — only iframe/JS embeds are counted.
 */
#[Fillable(['user_id', 'banner_key', 'day', 'impressions'])]
class AffiliateBannerImpression extends Model
{
    protected function casts(): array
    {
        return [
            'day' => 'date',
            'impressions' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
