<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Single-row (id=1) SMTP settings for AffilStack's own emails, edited in
 * Admin → Site → Platform Email. When switched on, applyToConfig() makes
 * them override the .env MAIL_* values for every request, queue job and
 * scheduled command. Credentials are encrypted at rest.
 */
#[Fillable(['is_enabled', 'credentials', 'last_tested_at', 'last_test_status', 'last_test_message'])]
class PlatformMailSetting extends Model
{
    protected const CACHE_KEY = 'platform_mail_settings:config';

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'credentials' => 'encrypted:array',
            'last_tested_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::query()->forceCreate(['id' => 1, 'is_enabled' => false]);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function isUsable(): bool
    {
        return $this->is_enabled
            && filled($this->credential('host'))
            && filled($this->credential('username'))
            && filled($this->credential('password'));
    }

    /**
     * The mail config these settings stand for (null when they shouldn't be used).
     *
     * @return array<string, mixed>|null
     */
    public function toMailConfig(): ?array
    {
        if (! $this->isUsable()) {
            return null;
        }

        $port = (int) ($this->credential('port') ?: 465);
        $from = (string) ($this->credential('from_address') ?: $this->credential('username'));

        return [
            'mail.default' => 'smtp',
            'mail.mailers.smtp.scheme' => $port === 465 ? 'smtps' : 'smtp',
            'mail.mailers.smtp.host' => (string) $this->credential('host'),
            'mail.mailers.smtp.port' => $port,
            'mail.mailers.smtp.username' => (string) $this->credential('username'),
            'mail.mailers.smtp.password' => (string) $this->credential('password'),
            'mail.from.address' => $from,
            'mail.from.name' => (string) ($this->credential('from_name') ?: config('app.name')),
        ];
    }

    /**
     * Applied once per request/job from AppServiceProvider. Never throws: a
     * missing table (fresh install) or a decryption problem leaves .env in charge.
     */
    public static function applyToConfig(): void
    {
        try {
            $config = Cache::rememberForever(self::CACHE_KEY, function (): array {
                return Schema::hasTable('platform_mail_settings') ? (static::current()->toMailConfig() ?? []) : [];
            });
        } catch (Throwable) {
            return;
        }

        if ($config !== []) {
            config($config);
            app()->forgetInstance('mail.manager');
            app()->forgetInstance('mailer');
        }
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget(self::CACHE_KEY));
        static::deleted(fn () => Cache::forget(self::CACHE_KEY));
    }
}
