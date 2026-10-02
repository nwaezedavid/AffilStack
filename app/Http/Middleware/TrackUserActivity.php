<?php

namespace App\Http\Middleware;

use App\Models\UserActivityDay;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps users.last_active_at fresh for the admin active/inactive view and
 * the re-engagement campaign. Throttled to one tiny UPDATE per user every
 * config('lifecycle.activity_write_interval_minutes') (5) — the check
 * reads the already-loaded user model, so most requests cost nothing.
 * Background polling (the notification bell) doesn't count as activity,
 * so a forgotten open tab doesn't keep someone "active" forever.
 */
class TrackUserActivity
{
    /**
     * @var array<int, string>
     */
    protected const IGNORED_ROUTES = ['notifications.poll'];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if (! $user || $request->routeIs(...self::IGNORED_ROUTES)) {
            return $response;
        }

        $interval = (int) config('lifecycle.activity_write_interval_minutes', 5);
        $lastActive = $user->last_active_at;

        if ($lastActive && $lastActive->greaterThan(now()->subMinutes($interval))) {
            return $response;
        }

        $now = now();

        DB::table('users')->where('id', $user->getKey())->update(['last_active_at' => $now]);
        $user->setRawAttributes(array_merge($user->getAttributes(), ['last_active_at' => $now->toDateTimeString()]), true);

        if (! $lastActive || ! $lastActive->isSameDay($now)) {
            UserActivityDay::query()->insertOrIgnore(['user_id' => $user->getKey(), 'date' => $now->toDateString()]);
        }

        return $response;
    }
}
