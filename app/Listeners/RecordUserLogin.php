<?php

namespace App\Listeners;

use App\Models\User;
use App\Models\UserActivityDay;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\DB;

/**
 * Stamps users.last_login_at (and last_active_at) on every successful
 * sign-in — password, two-factor, passkey, Google, "remember me", the
 * post-payment auto-login and the affiliate set-password flow all go
 * through Auth::login()/attempt(), which fire this one event, so no login
 * path can be missed. Written with a raw query so it never touches
 * updated_at or the activity log.
 */
class RecordUserLogin
{
    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        $now = now();

        DB::table('users')->where('id', $event->user->getKey())->update([
            'last_login_at' => $now,
            'last_active_at' => $now,
        ]);

        $event->user->setRawAttributes(array_merge($event->user->getAttributes(), [
            'last_login_at' => $now->toDateTimeString(),
            'last_active_at' => $now->toDateTimeString(),
        ]), true);

        UserActivityDay::query()->insertOrIgnore(['user_id' => $event->user->getKey(), 'date' => $now->toDateString()]);
    }
}
