<?php

namespace App\Http\Controllers\Developer;

use App\Http\Controllers\Controller;
use App\Services\Marketplace\DeveloperPayouts;
use App\Services\Referrals\PayoutMethodService;
use App\Services\Referrals\PaystackTransferService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * A developer's earnings from paid add-ons, their payout method (the same
 * one their account uses for referral payouts) and payout requests.
 */
class EarningsController extends Controller
{
    public function index(Request $request, DeveloperPayouts $payouts, PaystackTransferService $paystack): View
    {
        $user = $request->user();
        $developer = $user->developerProfile;
        $payouts->release();

        return view('developer.earnings', [
            'user' => $user,
            'developer' => $developer,
            'balance' => $payouts->balance($developer),
            'earnings' => $developer->earnings()->with('addon.liveVersion')->latest('occurred_at')->paginate(25),
            'payouts' => $user->referralPayouts()->where('source', 'developer')->latest('requested_at')->get(),
            'banks' => $paystack->banks(),
        ]);
    }

    public function saveMethod(Request $request, PayoutMethodService $methods): RedirectResponse
    {
        $methods->save($request->user(), $request->all());

        return redirect()->route('developer.earnings')->with('success', 'Payout details saved.');
    }

    public function resolveAccount(Request $request, PaystackTransferService $paystack): JsonResponse
    {
        $banks = $paystack->banks();

        $data = $request->validate([
            'bank_code' => ['required', 'string', 'max:20', $banks !== [] ? Rule::in(array_keys($banks)) : 'regex:/^[0-9A-Za-z]{2,20}$/'],
            'account_number' => ['required', 'digits:10'],
        ]);

        try {
            return response()->json(['account_name' => $paystack->resolveAccountName($data['account_number'], $data['bank_code'])]);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function requestPayout(Request $request, DeveloperPayouts $payouts): RedirectResponse
    {
        try {
            $payouts->requestPayout($request->user());
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested. We\'ll email you as soon as it\'s sent.');
    }
}
