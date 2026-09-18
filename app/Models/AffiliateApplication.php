<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * "Anyone can sign-up to become an affiliate without first becoming a user
 * of the platform... others will have to be manually approved by the admin
 * after they submit their application" — one row per submission on the
 * public affiliate landing page (affiliate.landing/affiliate.apply, see
 * routes/web.php). See AffiliateApplicationService for the whole
 * submit/approve/reject lifecycle, and User::is_affiliate_only for the
 * account this creates once approved.
 */
#[Fillable([
    'name', 'email', 'phone', 'website_url', 'promotion_channels', 'audience_size',
    'experience_level', 'message', 'status', 'rejection_reason',
    'applied_at', 'reviewed_at', 'reviewed_by_id', 'approved_user_id',
    'set_password_token', 'set_password_expires_at',
])]
class AffiliateApplication extends Model
{
    /**
     * Options for the audience_size field — landing-page select and the
     * Filament table/filter both read from this single source of truth.
     *
     * @return array<string, string>
     */
    public static function audienceSizeOptions(): array
    {
        return [
            'under_1k' => 'Under 1,000',
            '1k_10k' => '1,000 – 10,000',
            '10k_100k' => '10,000 – 100,000',
            'over_100k' => '100,000+',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function experienceLevelOptions(): array
    {
        return [
            'new' => 'New to affiliate marketing',
            'some_experience' => 'Some experience',
            'experienced' => 'Experienced affiliate marketer',
        ];
    }

    protected function casts(): array
    {
        return [
            'applied_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'set_password_expires_at' => 'datetime',
        ];
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_id');
    }

    /**
     * The affiliate-only User account this application created — null until
     * approved, and forever null for a rejected application.
     */
    public function approvedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Whether the plaintext token from a "set your password" link still
     * matches this application's stored hash and hasn't expired — the only
     * check that link's controller needs. See
     * AffiliateApplicationService::approve() for how the link is built.
     */
    public function hasValidSetPasswordToken(?string $token): bool
    {
        if (! $token || ! $this->set_password_token) {
            return false;
        }

        if ($this->set_password_expires_at && $this->set_password_expires_at->isPast()) {
            return false;
        }

        return hash_equals($this->set_password_token, hash('sha256', $token));
    }
}
