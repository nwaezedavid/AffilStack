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
#[Fillable(['is_enabled', 'gmail_sending_enabled', 'youtube_publishing_enabled', 'youtube_approval_status', 'youtube_approval_notes', 'credentials'])]
class GoogleOauthSetting extends Model
{
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'gmail_sending_enabled' => 'boolean',
            'youtube_publishing_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate(['id' => 1], ['is_enabled' => false]);
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
}
