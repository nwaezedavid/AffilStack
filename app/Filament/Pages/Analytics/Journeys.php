<?php

namespace App\Filament\Pages\Analytics;

use App\Filament\Clusters\Analytics;
use App\Filament\Concerns\ScopedToDepartment;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\User;
use App\Services\Analytics\AnalyticsProfileBuilder;
use App\Services\Analytics\JourneyTimeline;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;

/**
 * Agent 04: look up one person — by email, name, user id or the short
 * visitor id shown in exports — and see their whole journey: how they first
 * found the site, every visit, page, click, checkout and purchase, the
 * dashboard features they use, their cookie choices, and their interest
 * profile. Only people who allowed analytics have a journey.
 */
class Journeys extends Page
{
    use ScopedToDepartment;

    protected static string $department = 'site';

    protected static ?string $cluster = Analytics::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'Journeys';

    protected static ?string $title = 'Customer Journeys';

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.pages.analytics.journeys';

    #[Url]
    public string $search = '';

    #[Url(as: 'user')]
    public ?int $userId = null;

    #[Url(as: 'visitor')]
    public ?int $visitorId = null;

    public function updatedSearch(): void
    {
        $this->userId = null;
        $this->visitorId = null;
    }

    public function selectUser(int $id): void
    {
        $this->userId = $id;
        $this->visitorId = null;
    }

    public function selectVisitor(int $id): void
    {
        $this->visitorId = $id;
        $this->userId = null;
    }

    public function clearSelection(): void
    {
        $this->userId = null;
        $this->visitorId = null;
    }

    /**
     * @return array{users: Collection<int, User>, visitors: Collection<int, AnalyticsVisitor>}
     */
    #[Computed]
    public function matches(): array
    {
        $term = trim($this->search);

        if (mb_strlen($term) < 2) {
            return ['users' => collect(), 'visitors' => collect()];
        }

        $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term).'%';

        $users = User::query()
            ->where(fn ($q) => $q->where('email', 'like', $like)->orWhere('name', 'like', $like)->when(ctype_digit($term), fn ($q) => $q->orWhere('id', (int) $term)))
            ->orderByDesc('last_active_at')
            ->limit(8)
            ->get(['id', 'name', 'email', 'last_active_at']);

        $hex = strtolower(preg_replace('/[^0-9a-f]/i', '', $term) ?? '');
        $visitors = strlen($hex) >= 6
            ? AnalyticsVisitor::query()->where('uuid', 'like', substr($hex, 0, 8).'%')->limit(8)->get()
            : collect();

        return ['users' => $users, 'visitors' => $visitors];
    }

    /**
     * Recently active people with a profile, as a starting point.
     *
     * @return Collection<int, AnalyticsProfile>
     */
    #[Computed]
    public function recent(): Collection
    {
        return AnalyticsProfile::query()->with(['user:id,name,email', 'visitor:id,uuid'])->orderByDesc('last_seen_at')->limit(12)->get();
    }

    #[Computed]
    public function selectedUser(): ?User
    {
        return $this->userId ? User::query()->find($this->userId) : null;
    }

    #[Computed]
    public function selectedVisitor(): ?AnalyticsVisitor
    {
        return $this->visitorId ? AnalyticsVisitor::query()->find($this->visitorId) : null;
    }

    #[Computed]
    public function profile(): ?AnalyticsProfile
    {
        if ($this->selectedUser) {
            return AnalyticsProfile::query()->where('user_id', $this->selectedUser->id)->first();
        }

        if ($this->selectedVisitor) {
            return $this->selectedVisitor->user_id
                ? AnalyticsProfile::query()->where('user_id', $this->selectedVisitor->user_id)->first()
                : AnalyticsProfile::query()->where('visitor_id', $this->selectedVisitor->id)->first();
        }

        return null;
    }

    /**
     * @return Collection<int, AnalyticsVisitor>
     */
    #[Computed]
    public function devices(): Collection
    {
        if ($this->selectedUser) {
            return AnalyticsVisitor::query()->where('user_id', $this->selectedUser->id)->orderByDesc('last_seen_at')->get();
        }

        return $this->selectedVisitor ? collect([$this->selectedVisitor]) : collect();
    }

    #[Computed]
    public function consent(): ?ConsentRecord
    {
        if ($this->selectedUser) {
            return ConsentRecord::latestForUser($this->selectedUser->id);
        }

        return $this->selectedVisitor ? ConsentRecord::query()->where('visitor_id', $this->selectedVisitor->id)->orderByDesc('id')->first() : null;
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    #[Computed]
    public function timeline(): Collection
    {
        $journeys = app(JourneyTimeline::class);

        if ($this->selectedUser) {
            return $journeys->forUser($this->selectedUser);
        }

        return $this->selectedVisitor ? $journeys->forVisitor($this->selectedVisitor) : collect();
    }

    public function refreshProfile(): void
    {
        $builder = app(AnalyticsProfileBuilder::class);
        $profile = $this->selectedUser
            ? $builder->buildForUser($this->selectedUser)
            : ($this->selectedVisitor ? $builder->buildForVisitor($this->selectedVisitor) : null);

        unset($this->profile);

        Notification::make()
            ->title($profile ? 'Profile refreshed' : 'No profile — this person hasn\'t allowed analytics')
            ->{$profile ? 'success' : 'warning'}()
            ->send();
    }
}
