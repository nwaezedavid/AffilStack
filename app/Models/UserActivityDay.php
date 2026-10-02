<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One row per user per day they used the dashboard — written by
 * TrackUserActivity the first time a user is seen each day (one cheap
 * insert-or-ignore), and read by the admin "User activity" widget for its
 * daily-active-users sparkline. Pruned after config('lifecycle.activity_history_days').
 */
#[Fillable(['user_id', 'date'])]
class UserActivityDay extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
