<?php

namespace App\Support\Mobile;

use App\Models\User;

/**
 * The website's team-seat rules (RestrictAgencySeats) for the phone app,
 * keyed by the website route a feature lives on, so a seat can do in the
 * app exactly what it can do on the dashboard and nothing more.
 */
class SeatAccess
{
    public static function allows(User $user, string $webRouteName): bool
    {
        if (! $user->isSeat()) {
            return true;
        }

        if (in_array($webRouteName, (array) config('agency.shared_only_routes'), true)) {
            return $user->hasSharedTeamAccess();
        }

        return in_array($webRouteName, (array) config('agency.seat_allowed_routes'), true);
    }

    /**
     * 403 with the dashboard's own wording when a seat may not use it.
     */
    public static function authorize(User $user, string $webRouteName): void
    {
        abort_unless(static::allows($user, $webRouteName), 403, 'This account is a team seat and only has access to its assigned offer.');
    }
}
