<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * One admin-editable lifecycle email campaign — the birthday email, one
 * festive-season greeting (each festival is its own row, with its own date
 * rule and audience), or one step of the re-engagement sequence. Presets
 * come from LifecycleEmailPresets; the admin edits copy, imagery, timing
 * and on/off from Support > Lifecycle Emails. Sending is driven by the
 * lifecycle:* scheduled commands through LifecycleEmailDispatcher, and
 * every send is recorded once per occurrence in LifecycleEmailLog.
 *
 * date_rule shapes (festive only — see FestivalCalendar):
 *   {"type":"fixed","month":12,"day":25}
 *   {"type":"easter","offset":-2}
 *   {"type":"nth_weekday","month":11,"weekday":4,"n":4}   (ISO weekday, n=-1 = last)
 *   {"type":"table","dates":["2026-03-20", ...]}           (lunar calendars)
 *   {"type":"by_country","default":{...},"countries":{"GB":{...}}}
 *
 * date_overrides: [{"year":2027,"date":"2027-03-10"}, ...] — when a year
 * has any override rows they replace the computed date(s) for that year.
 */
#[Fillable([
    'key', 'type', 'name', 'subject', 'preheader', 'heading', 'body', 'cta_label', 'cta_url',
    'hero_image_path', 'image_hint', 'theme_color', 'enabled', 'category', 'religion', 'countries',
    'date_rule', 'date_overrides', 'days_before', 'inactive_days', 'bonus_credits', 'admin_notes', 'sort_order',
])]
class LifecycleEmail extends Model
{
    public const TYPE_BIRTHDAY = 'birthday';

    public const TYPE_FESTIVE = 'festive';

    public const TYPE_REENGAGEMENT = 'reengagement';

    public const CATEGORY_RELIGIOUS = 'religious';

    public const CATEGORY_NATIONAL = 'national';

    public const CATEGORY_CULTURAL = 'cultural';

    public const CATEGORY_UNIVERSAL = 'universal';

    /**
     * When two festivals land on the same day for one user, the higher
     * number wins (the brief: religious > national > universal) — a user
     * never gets more than one festive email per day.
     *
     * @var array<string, int>
     */
    public const CATEGORY_PRIORITY = [
        self::CATEGORY_RELIGIOUS => 40,
        self::CATEGORY_NATIONAL => 30,
        self::CATEGORY_CULTURAL => 20,
        self::CATEGORY_UNIVERSAL => 10,
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'countries' => 'array',
            'date_rule' => 'array',
            'date_overrides' => 'array',
            'days_before' => 'integer',
            'inactive_days' => 'integer',
            'bonus_credits' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LifecycleEmailLog::class);
    }

    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('enabled', true);
    }

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function priority(): int
    {
        return self::CATEGORY_PRIORITY[$this->category ?? self::CATEGORY_UNIVERSAL] ?? 0;
    }

    public function heroImageUrl(): ?string
    {
        return $this->hero_image_path ? Storage::disk('public')->url($this->hero_image_path) : null;
    }

    /**
     * @return array<int, string> upper-cased ISO country codes this email is gated to (empty = everyone)
     */
    public function countryCodes(): array
    {
        return array_values(array_filter(array_map(
            fn ($code) => strtoupper(trim((string) $code)),
            $this->countries ?? [],
        )));
    }
}
