<?php

namespace App\Models;

use Database\Factories\ReferralCampaignFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A partner's labelled tracked link ("YouTube review", "Newsletter June"):
 * /r/{code}?c={slug}. Clicks, sign-ups and commissions that came through
 * it carry its id, so the Partner Portal can report each campaign.
 */
#[Fillable(['user_id', 'name', 'slug', 'channel', 'landing_path'])]
class ReferralCampaign extends Model
{
    /** @use HasFactory<ReferralCampaignFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    public static function channels(): array
    {
        return [
            'website' => 'Website or blog',
            'youtube' => 'YouTube',
            'email' => 'Email newsletter',
            'social' => 'Social media',
            'messaging' => 'WhatsApp or Telegram',
            'ads' => 'Paid ads',
            'other' => 'Other',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ReferralClick::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class);
    }

    public function channelLabel(): string
    {
        return static::channels()[$this->channel] ?? ucfirst($this->channel);
    }
}
