<?php

namespace App\Support\Lifecycle;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Activity status shown in the admin Users list and stats widget, from
 * users.last_active_at (written by TrackUserActivity at most every few
 * minutes) falling back to last_login_at:
 *   active   — active within LifecycleSettings::activeWithinDays() (7)
 *   idle     — between that and inactiveAfterDays() (30)
 *   inactive — longer ago than that
 *   never    — has never signed in
 */
class UserActivity
{
    public const ACTIVE = 'active';

    public const IDLE = 'idle';

    public const INACTIVE = 'inactive';

    public const NEVER = 'never';

    /**
     * @var array<string, string>
     */
    public const LABELS = [
        self::ACTIVE => 'Active',
        self::IDLE => 'Idle',
        self::INACTIVE => 'Inactive',
        self::NEVER => 'Never logged in',
    ];

    /**
     * @var array<string, string>
     */
    public const COLORS = [
        self::ACTIVE => 'success',
        self::IDLE => 'warning',
        self::INACTIVE => 'danger',
        self::NEVER => 'gray',
    ];

    public static function lastSeen(User $user): ?CarbonInterface
    {
        return $user->last_active_at ?? $user->last_login_at;
    }

    public static function statusFor(User $user): string
    {
        $lastSeen = self::lastSeen($user);

        if ($lastSeen === null) {
            return self::NEVER;
        }

        if ($lastSeen->greaterThanOrEqualTo(now()->subDays(LifecycleSettings::activeWithinDays()))) {
            return self::ACTIVE;
        }

        if ($lastSeen->greaterThanOrEqualTo(now()->subDays(LifecycleSettings::inactiveAfterDays()))) {
            return self::IDLE;
        }

        return self::INACTIVE;
    }

    /**
     * Narrows a users query to one activity status — used by the admin
     * Users tabs/filter and the stats widget, so counts and lists always
     * agree with the badge.
     */
    public static function scope(Builder $query, string $status): Builder
    {
        $activeSince = now()->subDays(LifecycleSettings::activeWithinDays());
        $inactiveBefore = now()->subDays(LifecycleSettings::inactiveAfterDays());

        return match ($status) {
            self::ACTIVE => $query->where(fn ($q) => $q
                ->where('last_active_at', '>=', $activeSince)
                ->orWhere(fn ($q) => $q->whereNull('last_active_at')->where('last_login_at', '>=', $activeSince))),
            self::IDLE => $query->where(fn ($q) => $q
                ->where(fn ($q) => $q->where('last_active_at', '>=', $inactiveBefore)->where('last_active_at', '<', $activeSince))
                ->orWhere(fn ($q) => $q->whereNull('last_active_at')->where('last_login_at', '>=', $inactiveBefore)->where('last_login_at', '<', $activeSince))),
            self::INACTIVE => $query->where(fn ($q) => $q
                ->where('last_active_at', '<', $inactiveBefore)
                ->orWhere(fn ($q) => $q->whereNull('last_active_at')->where('last_login_at', '<', $inactiveBefore))),
            self::NEVER => $query->whereNull('last_active_at')->whereNull('last_login_at'),
            default => $query,
        };
    }
}
