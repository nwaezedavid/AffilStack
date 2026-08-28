<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Generation;
use App\Models\Offer;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Feature 10 (Phase 3 backlog, item 10): agency/team seats. A seat is a
 * normal User row (agency_owner_id set) that logs in on its own but bills
 * every credit to its owner (User::billableUser(), enforced centrally in
 * CreditManager) and is scoped to exactly one offer (seat_offer_id) — the
 * "draft only, no publish; one product only" backlog wording, enforced by
 * RestrictAgencySeats plus Offer::isAccessibleBy() / Generation::isAccessibleBy()
 * everywhere a controller used to check raw ownership. How many seats a
 * plan includes already existed as Plan::team_seats, unused until now.
 */
class TeamController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $seats = $user->seats()->with('seatOffer')->latest()->get();
        $offers = $user->offers()->orderBy('product_name')->get();

        return view('dashboard.team.index', compact('seats', 'offers'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = auth()->user();

        if ($user->agencySeatsRemaining() < 1) {
            return back()->with('error', 'You\'ve used every team seat your plan includes — upgrade to add more.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'offer_id' => ['required', 'integer'],
        ]);

        $offer = Offer::findOrFail($validated['offer_id']);
        abort_unless($offer->user_id === $user->id, 403);

        $password = Str::password(16);

        $seat = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($password),
            'agency_owner_id' => $user->id,
            'seat_offer_id' => $offer->id,
            'credits_balance' => 0,
        ]);
        $seat->assignRole('user');

        return back()->with(
            'success',
            "Seat created for {$seat->name} ({$seat->email}), scoped to \"{$offer->product_name}\". ".
            "Temporary password: {$password} — share it with them securely; this won't be shown again."
        );
    }

    public function destroy(User $seat): RedirectResponse
    {
        $user = auth()->user();

        abort_unless($seat->agency_owner_id === $user->id, 403);

        // generations.user_id cascade-deletes with its user, so reassign the
        // seat's drafts to the owner first rather than losing their work.
        Generation::where('user_id', $seat->id)->update(['user_id' => $user->id]);

        $seatName = $seat->name;
        $seat->delete();

        return back()->with('success', "Removed {$seatName}'s seat — the content they generated is still here under your account.");
    }
}
