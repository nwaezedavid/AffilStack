<?php

namespace App\Models;

use Database\Factories\TeamMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * One person in the About page's team grid (CEO, CFO, COO, CTO…), shown
 * next to the founder section. Admin-managed (Site > Team Members); nothing
 * is ever seeded, so the section stays hidden until real people are added
 * — the same "never fabricate" rule the founder, Testimonials and Brand
 * Logos follow.
 */
#[Fillable(['name', 'role', 'bio', 'photo_path', 'linkedin_url', 'x_url', 'website_url', 'sort_order', 'is_visible'])]
class TeamMember extends Model
{
    /** @use HasFactory<TeamMemberFactory> */
    use HasFactory;

    /**
     * Roles offered as suggestions in the admin form — any title can still
     * be typed.
     *
     * @var array<int, string>
     */
    public const SUGGESTED_ROLES = [
        'Co-founder', 'Chief Executive Officer (CEO)', 'Chief Operating Officer (COO)', 'Chief Financial Officer (CFO)',
        'Chief Technology Officer (CTO)', 'Chief Marketing Officer (CMO)', 'Head of Product', 'Head of Growth',
        'Head of Customer Success', 'Lead Engineer', 'Community Manager', 'Advisor',
    ];

    /**
     * Cached indefinitely and busted on save/delete — same pattern as
     * BrandLogo::activePublicList(). Caches plain attribute arrays, never
     * models (see Plan::activePublicList() for why).
     *
     * @return Collection<int, self>
     */
    public static function visibleList(): Collection
    {
        $rows = Cache::rememberForever('team_members:visible_list', function () {
            return static::where('is_visible', true)->orderBy('sort_order')->orderBy('id')->get()
                ->map(fn (self $member) => $member->getAttributes())->all();
        });

        return static::hydrate($rows);
    }

    protected static function booted(): void
    {
        static::saved(fn () => Cache::forget('team_members:visible_list'));
        static::deleted(fn () => Cache::forget('team_members:visible_list'));
    }

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    public function initials(): string
    {
        $words = preg_split('/\s+/', trim($this->name)) ?: [];

        return strtoupper(implode('', array_map(fn (string $word) => mb_substr($word, 0, 1), array_slice($words, 0, 2))));
    }

    /**
     * The member's profile links that are set and safe to render.
     *
     * @return array<string, string> brand key (linkedin|x|website) => URL
     */
    public function profileLinks(): array
    {
        return array_filter([
            'linkedin' => $this->linkedin_url,
            'x' => $this->x_url,
            'website' => $this->website_url,
        ], fn (?string $url) => $url !== null && preg_match('#^https?://#i', $url) === 1);
    }
}
