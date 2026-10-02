<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's own connected email sender for CRM nurture sends (task #1) —
 * either 'gmail' (OAuth, tokens in $credentials) or 'smtp' (host/port/
 * username/password/from_email/from_name in $credentials). See
 * App\Services\Crm\PersonalEmailSender for how either provider is
 * actually used to send, and CrmEmailService for why this is preferred
 * exclusively over the platform's own mailer once it exists.
 */
#[Fillable([
    'user_id', 'provider', 'preset', 'credentials', 'connected_email', 'verified_at', 'verification_status',
    'verification_message', 'last_checked_at', 'daily_limit', 'footer_business_name', 'footer_address',
    'imap_credentials', 'imap_status', 'imap_message', 'imap_verified_at', 'imap_uidvalidity', 'imap_last_uid',
    'imap_last_polled_at', 'gmail_read_granted', 'gmail_history_id', 'gmail_last_polled_at',
])]
class EmailConnection extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'imap_credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'imap_verified_at' => 'datetime',
            'imap_last_polled_at' => 'datetime',
            'gmail_last_polled_at' => 'datetime',
            'gmail_read_granted' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function isGmail(): bool
    {
        return $this->provider === 'gmail';
    }

    public function isSmtp(): bool
    {
        return $this->provider === 'smtp';
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'success';
    }

    public function imapCredential(string $key, mixed $default = null): mixed
    {
        return data_get($this->imap_credentials, $key, $default);
    }

    public function hasImap(): bool
    {
        return filled($this->imapCredential('host')) && filled($this->imapCredential('username'));
    }

    public function isImapVerified(): bool
    {
        return $this->hasImap() && $this->imap_status === 'success';
    }

    /**
     * Gmail API inbox reading — only when the admin has switched it on
     * (Google CASA approval) AND this user granted gmail.readonly.
     */
    public function canReadViaGmailApi(): bool
    {
        return $this->isGmail() && $this->gmail_read_granted && GoogleOauthSetting::current()->gmailReadingAvailable();
    }

    /**
     * Which reply-detection channel is live for this mailbox, if any.
     */
    public function replyDetectionMethod(): ?string
    {
        return match (true) {
            $this->canReadViaGmailApi() => 'gmail',
            $this->isImapVerified() => 'imap',
            default => null,
        };
    }

    /**
     * The effective daily cap: the provider ceiling from config('crm'),
     * lowered by the user's own limit when they set one.
     */
    public function dailyCap(): int
    {
        $ceiling = (int) config('crm.daily_caps.'.($this->isGmail() ? 'gmail' : 'smtp'), 300);

        return $this->daily_limit ? min($ceiling, (int) $this->daily_limit) : $ceiling;
    }

    /**
     * Status for <x-ui.status-pill> on the sending side.
     */
    public function sendingPillStatus(): string
    {
        return match ($this->verification_status) {
            'success' => 'verified',
            'failed' => 'failed',
            default => 'connected',
        };
    }

    /**
     * Status for <x-ui.status-pill> on the reply-detection side.
     */
    public function imapPillStatus(): string
    {
        if (! $this->hasImap()) {
            return 'not_connected';
        }

        return match ($this->imap_status) {
            'success' => 'verified',
            'failed' => 'failed',
            default => 'connected',
        };
    }
}
