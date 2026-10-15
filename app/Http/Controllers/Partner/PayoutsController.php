<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Services\Referrals\PartnerStatsService;
use App\Services\Referrals\PayoutMethodService;
use App\Services\Referrals\PaystackTransferService;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

/**
 * Earnings & payouts: balances, where to be paid (Nigerian bank via
 * Paystack, PayPal or USDT), payout requests (approved by the admin
 * before anything is sent), payout history and referred customers.
 */
class PayoutsController extends Controller
{
    public function index(Request $request, PartnerStatsService $stats, PaystackTransferService $paystack): View
    {
        $user = $request->user();

        return view('partner.payouts', [
            'user' => $user,
            'balances' => $stats->balances($user),
            'balancesByCurrency' => $user->unpaidApprovedCommissionByCurrency(),
            'payouts' => $user->referralPayouts()->latest('requested_at')->get(),
            'referrals' => $stats->recentReferrals($user, 100),
            'banks' => $paystack->banks(),
        ]);
    }

    public function saveMethod(Request $request, PayoutMethodService $methods): RedirectResponse
    {
        $methods->save($request->user(), $request->all());

        return redirect()->route('partner.payouts')->with('success', 'Payout details saved.');
    }

    /**
     * Looks up the account holder's name as the partner types their
     * account number, so they can confirm it before saving.
     */
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

    public function requestPayout(Request $request, ReferralPayoutService $service): RedirectResponse
    {
        $currency = $request->string('currency')->upper()->value() ?: null;

        try {
            $service->requestPayout($request->user(), $currency);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested. We\'ll email you as soon as it\'s sent.');
    }
}
