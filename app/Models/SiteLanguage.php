<?php

namespace App\Models;

use App\Support\Translation\Languages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A language the site is offered in besides English (the source). "draft"
 * languages are being translated and only admins see them; "live" ones
 * appear in the language switcher, get /{code}/ addresses and are
 * suggested to matching visitors.
 */
#[Fillable(['code', 'name', 'native_name', 'status', 'rtl', 'sort_order'])]
class SiteLanguage extends Model
{
    protected function casts(): array
    {
        return [
            'rtl' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saved(fn () => Languages::flush());
        static::deleted(fn () => Languages::flush());
    }

    public function translations(): HasMany
    {
        return $this->hasMany(Translation::class, 'locale', 'code');
    }

    public function isLive(): bool
    {
        return $this->status === 'live';
    }
}
