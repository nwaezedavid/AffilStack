<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Single-row (id=1) admin configuration for "Continue with Google" — see
 * Filament: Site > Google Login and App\Services\Auth\GoogleOAuthService.
 * Credentials are encrypted at rest, same as PaymentGatewaySetting.
 *
 * gmail_sending_enabled (task #1) and youtube_publishing_enabled (task #2)
 * each reuse this SAME client id/secret for their own separate consent
 * flow — a user connecting their own Gmail (scope: gmail.send) or YouTube
 * channel (scope: youtube.upload) — see App\Services\Crm\GmailOAuthService
 * and App\Services\Social\YouTubePublishingService. Each is its own toggle
 * rather than folded into is_enabled because both are Google-restricted
 * scopes: the OAuth consent screen needs to pass Google's security
 * assessment (Gmail) or the YouTube Data API needs its own Audit + Quota
 * Extension (YouTube) before either actually works for anyone outside the
 * app's own test users — independently of each other and of when
 * "Continue with Google" already works.
 */
#[Fillable(['is_enabled', 'gmail_sending_enabled', 'gmail_reading_enabled', 'youtube_publishing_enabled', 'youtube_approval_status', 'youtube_approval_notes', 'limit_to_review_testers', 'review_testers', 'credentials'])]
class GoogleOauthSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'gmail_sending_enabled' => 'boolean',
            'gmail_reading_enabled' => 'boolean',
            'youtube_publishing_enabled' => 'boolean',
            'limit_to_review_testers' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        // Not firstOrCreate(['id' => 1]): 'id' isn't fillable, so the INSERT silently
        // used the next auto-increment value instead — and on MariaDB/MySQL that
        // isn't 1 once any insert has been rolled back — so every call after
        // that created a fresh empty row and saved settings looked lost.
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1] + ['is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function gmailSendingAvailable(): bool
    {
        return $this->gmail_sending_enabled && $this->credential('client_id') && $this->credential('client_secret');
    }

    /**
     * Gmail API inbox reading (gmail.readonly, a restricted scope) for CRM
     * reply detection — off until Google's CASA assessment is approved, and
     * only meaningful while Gmail sending itself is available.
     */
    public function gmailReadingAvailable(): bool
    {
        return $this->gmail_reading_enabled && $this->gmailSendingAvailable();
    }

    /**
     * Whether a user can even start connecting a YouTube channel — the
     * toggle plus credentials, same shape as gmailSendingAvailable().
     * Separate from isYoutubeApprovedForPublishing(): a channel can be
     * connected (for context/identity) well before YouTube has approved
     * real uploads.
     */
    public function youtubePublishingAvailable(): bool
    {
        return $this->youtube_publishing_enabled && $this->credential('client_id') && $this->credential('client_secret');
    }

    /**
     * Whether YouTube has actually granted the Audit + Quota Extension
     * this app needs for videos.insert to work for real users — until
     * then, publish attempts fall back to "download and post manually".
     */
    public function isYoutubeApprovedForPublishing(): bool
    {
        return $this->youtube_approval_status === 'approved';
    }

    /**
     * The test accounts listed for Google's review (one email per line or
     * comma-separated), lower-cased.
     *
     * @return array<int, string>
     */
    public function reviewTesterEmails(): array
    {
        return collect(preg_split('/[\s,;]+/', (string) $this->review_testers))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter(fn (string $email): bool => str_contains($email, '@'))
            ->unique()
            ->values()
            ->all();
    }

    /**
     * A listed review test account, or an admin (who records the demo).
     * Testers can also publish to YouTube for real before the Audit +
     * Quota Extension status is Approved.
     */
    public function isReviewTester(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return in_array(strtolower((string) $user->email), $this->reviewTesterEmails(), true)
            || $user->hasAnyRole(['admin', 'super-admin']);
    }

    /**
     * Whether this user may start a Gmail or YouTube connection. While
     * "limit to review testers" is on, everyone else keeps SMTP and manual
     * posting, so customers never see Google's unverified-app screen or use
     * up the 100-user cap.
     */
    public function sensitiveConnectionsOpenTo(?User $user): bool
    {
        return ! $this->limit_to_review_testers || $this->isReviewTester($user);
    }
}
