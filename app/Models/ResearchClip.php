<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 11 (Phase 3 backlog, item 11): a page the browser capture
 * extension saved — a product page, a competitor ad, a LinkedIn post, or
 * anything else worth keeping while researching an offer. Exists
 * independently of any offer until the user attaches it (or starts a new
 * offer directly from it, which attaches it automatically) — see
 * ExtensionController and OfferController::store().
 */
#[Fillable(['user_id', 'offer_id', 'source_url', 'title', 'selected_text', 'page_type'])]
class ResearchClip extends Model
{
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }
}
