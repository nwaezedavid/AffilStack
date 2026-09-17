<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Audit gap #1: User::is_suspended has only ever blocked the ADMIN panel
 * (see User::canAccessPanel()) — a suspended customer could keep using
 * their own dashboard, generating content and spending credits, until an
 * admin took some other action to actually stop them. Applied to the whole
 * customer-dashboard route group (see routes/web.php), alongside
 * RestrictAgencySeats.
 *
 * Checked against billableUser() too, not just the request user, so
 * suspending an agency owner also locks out every team seat billed to
 * them — the same choke point CreditManager/canUseChannel() already use,
 * rather than a second suspension flag per seat.
 */
class EnsureAccountNotSuspended
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || (! $user->is_suspended && ! $user->billableUser()->is_suspended)) {
            return $next($request);
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')
            ->with('error', 'Your account has been suspended. Contact support if you believe this is a mistake.');
    }
}
