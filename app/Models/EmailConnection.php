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
#[Fillable(['user_id', 'provider', 'credentials', 'connected_email', 'verified_at', 'verification_status', 'verification_message'])]
class EmailConnection extends Model
{
    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'verified_at' => 'datetime',
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
}
