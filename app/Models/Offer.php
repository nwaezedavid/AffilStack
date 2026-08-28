<?php

namespace App\Models;

use App\Services\Compliance\DisclosureService;
use App\Services\Links\LinkCloakingService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id', 'product_name', 'product_url', 'affiliate_network', 'status',
    'ideal_customer_summary', 'where_to_find', 'recommended_channel',
    'recommended_angle', 'research_data', 'disclosure_country',
])]
class Offer extends Model
{
    /**
     * Kept as a constant (rather than typed inline in Blade templates) so
     * the literal "{{...}}" text lives only in PHP files — Blade's own
     * "{{ }}" echo-detection is a naive text scan over the whole compiled
     * template, so the same literal written inside a .blade.php file (even
     * inside a quoted string argument to @if) gets mistaken for an echo
     * and silently corrupts the surrounding directive.
     */
    public const LINK_PLACEHOLDER = '{{AFFILIATE_LINK}}';

    protected function casts(): array
    {
        return [
            'research_data' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether $user may view/generate content for this offer — its owner,
     * or a team seat (item 10) specifically scoped to this one offer. Every
     * controller that used to check `$offer->user_id === auth()->id()`
     * routes through this instead, so seats work without each of those
     * checks needing to know seats exist.
     */
    public function isAccessibleBy(User $user): bool
    {
        if ($this->user_id === $user->id) {
            return true;
        }

        return $user->isSeat() && $user->seat_offer_id === $this->id;
    }

    public function generations(): HasMany
    {
        return $this->hasMany(Generation::class);
    }

    public function trackedLinks(): HasMany
    {
        return $this->hasMany(TrackedLink::class);
    }

    public function earnings(): HasMany
    {
        return $this->hasMany(Earning::class);
    }

    /**
     * Replace the "{{AFFILIATE_LINK}}" placeholder AI-generated content uses
     * with this offer's cloaked /go/ link for the given content channel —
     * lazily creating that link (feature 1) the first time it's needed, so
     * an offer with no generated content yet never gets one. Text without
     * the placeholder passes through untouched (and without creating a link).
     *
     * Also ensures the jurisdiction-correct affiliate disclosure wording
     * (feature 3) is present, since text carrying an affiliate link is
     * exactly the text FTC/ASA/etc-style disclosure rules are about. Pass
     * $withDisclosure = false for working documents that aren't themselves
     * published (a YouTube recording script, a TikTok voiceover script) —
     * their public surface (description/caption) already carries it.
     */
    public function cloak(?string $text, string $module = 'general', bool $withDisclosure = true): string
    {
        if (! $this->hasLinkPlaceholder($text)) {
            return $text ?? '';
        }

        $link = app(LinkCloakingService::class)->getOrCreateForOffer($this, $module);
        $text = str_replace(self::LINK_PLACEHOLDER, $link->short_url, $text);

        if (! $withDisclosure) {
            return $text;
        }

        return app(DisclosureService::class)->ensure($text, $this->disclosure_country, $module);
    }

    /**
     * Whether $text is public-facing affiliate content at all — i.e. it
     * carries the link placeholder cloak() would act on. Used by the view
     * to decide whether a disclosure badge applies before checking status.
     */
    public function hasLinkPlaceholder(?string $text): bool
    {
        return $text !== null && str_contains($text, self::LINK_PLACEHOLDER);
    }

    /**
     * Whether $text carries an affiliate link but no disclosure yet — used
     * by the UI to badge a piece of content "disclosure added automatically"
     * vs. "already disclosed", checked against the raw stored text (before
     * cloak() runs) so the badge reflects what the AI actually wrote.
     */
    public function needsDisclosure(?string $text): bool
    {
        if (! $this->hasLinkPlaceholder($text)) {
            return false;
        }

        return ! app(DisclosureService::class)->hasDisclosure($text);
    }
}
