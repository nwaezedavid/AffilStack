<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\ApiToken;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Task #6 (general API + team automation): the dashboard side of the
 * general-purpose API. Reuses ApiToken exactly as the browser extension
 * does (ExtensionController) — a token created here works there and vice
 * versa, since every /v1/* endpoint scopes its own response to the
 * token's owner rather than the page that minted it. Deliberately open to
 * team seats (see config('agency.seat_allowed_routes')), unlike the
 * extension: a seat's own token naturally only ever sees what that seat
 * can already see (visibleOffers()/visibleGenerations() for a shared-plan
 * seat, its one assigned offer for an isolated one).
 */
class ApiAccessController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $tokens = $user->apiTokens()->where('type', 'user')->latest()->get();

        return view('dashboard.api-access.index', compact('tokens'));
    }

    public function createToken(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $result = ApiToken::generate(auth()->user(), $validated['name']);

        return back()->with(
            'success',
            "Token created — copy it now, it won't be shown again:\n{$result['plainText']}"
        );
    }

    public function revokeToken(ApiToken $token): RedirectResponse
    {
        abort_unless($token->user_id === auth()->id(), 403);

        $token->delete();

        return back()->with('success', 'Token revoked — anything using it will get a 401 from now on.');
    }
}
