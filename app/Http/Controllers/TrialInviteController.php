<?php

namespace App\Http\Controllers;

use App\Actions\Fortify\PasswordValidationRules;
use App\Models\TrialInvite;
use App\Models\User;
use App\Services\Payments\CheckoutCountryResolver;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Trials\TrialInviteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * The only way into an admin-only free trial: the signed link from the
 * invite email. Opening it remembers the invite in the session; the
 * invitee then creates an account (or signs in with the invited email) and
 * saves a card, which starts the trial — see TrialInviteService.
 */
class TrialInviteController extends Controller
{
    use PasswordValidationRules;

    public function __construct(protected TrialInviteService $trials) {}

    public function show(Request $request, TrialInvite $invite, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): View
    {
        $request->session()->put('trial_invite_id', $invite->id);
        $user = $request->user();
        $blockingReason = ! $invite->isRedeemable()
            ? 'This invite has already been used or has expired.'
            : ($user ? $this->trials->acceptBlockingReason($invite, $user) : null);

        if (! $user && $invite->isRedeemable()) {
            // Back here after signing in.
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return view('trial-invites.show', [
            'invite' => $invite->load('plan'),
            'user' => $user,
            'blockingReason' => $blockingReason,
            'accountExists' => User::where('email', $invite->email)->exists(),
            'gateways' => $this->trials->cardGateways($gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US')),
            'cardCheckMinor' => (int) config('customer_wallet.trial.card_check_minor', 100),
        ]);
    }

    public function register(Request $request, TrialInvite $invite): RedirectResponse
    {
        $this->ensureOpenedFromLink($request, $invite);

        if (User::where('email', $invite->email)->exists()) {
            return redirect()->route('login')->with('error', 'An account with this email already exists — sign in to continue.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => $this->passwordRules(),
            'accepts_refund_policy' => ['accepted'],
        ], [
            'accepts_refund_policy.accepted' => 'You must accept the Refund & Cancellation Policy to continue.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $invite->email,
            'password' => Hash::make($validated['password']),
            'refund_policy_accepted_at' => now(),
        ]);

        // The invite email proved they own this address.
        $user->forceFill(['email_verified_at' => now()])->save();
        $user->assignRole('user');

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('trial_invite_id', $invite->id);

        return redirect()->to($this->trials->signedUrl($invite));
    }

    public function start(Request $request, TrialInvite $invite, PaymentGatewayManager $gateways, CheckoutCountryResolver $countries): RedirectResponse
    {
        $this->ensureOpenedFromLink($request, $invite);

        $available = $this->trials->cardGateways($gateways->enabledForCountry($countries->isNigeria($request) ? 'NG' : 'US'));

        if (empty($available)) {
            return back()->with('error', 'Card payments are temporarily unavailable — please try again shortly.');
        }

        $gateway = collect($available)->first(fn ($g) => $g->key() === $request->input('gateway')) ?? $available[0];

        try {
            $checkout = $this->trials->startCardCheck($invite, $request->user(), $gateway);
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (RuntimeException $e) {
            report($e);

            return back()->with('error', 'We could not start the card check right now. Please try again in a moment.');
        }

        return redirect()->away($checkout['link']);
    }

    /**
     * The register/start forms only work for the invite whose signed link
     * was opened in this browser session.
     */
    protected function ensureOpenedFromLink(Request $request, TrialInvite $invite): void
    {
        abort_unless((int) $request->session()->get('trial_invite_id') === $invite->id, 403, 'Open the link from your invite email to continue.');
        abort_unless($invite->isRedeemable(), 410, 'This invite has already been used or has expired.');
    }
}
