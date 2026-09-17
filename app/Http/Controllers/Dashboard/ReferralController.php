<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Services\Referrals\ReferralPayoutService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use RuntimeException;

class ReferralController extends Controller
{
    public function index(): View
    {
        $user = auth()->user();

        $referrals = $user->referrals()
            ->with(['referredUser', 'events'])
            ->latest()
            ->get();

        $clickCount = $user->referralClicks()->count();

        $earningsByStatus = $referrals
            ->flatMap(fn ($referral) => $referral->events)
            ->groupBy('status')
            ->map(fn ($events) => $events->sum('amount_cents'));

        $totals = [
            'pending_cents' => $earningsByStatus->get('pending', 0),
            'approved_cents' => $earningsByStatus->get('approved', 0),
            'paid_cents' => $earningsByStatus->get('paid', 0),
        ];

        $payouts = $user->referralPayouts()->latest('requested_at')->get();

        return view('dashboard.referrals.index', compact('user', 'referrals', 'clickCount', 'totals', 'payouts'));
    }

    /**
     * Saves (or replaces) the affiliate's payout profile. The fields
     * required depend on the chosen method — validated here rather than a
     * Form Request since the shape of "details" is entirely method-driven.
     */
    public function savePayoutMethod(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'payout_method' => ['required', 'string', 'in:'.implode(',', array_keys(config('referrals.payout_methods')))],
            'paypal_email' => ['required_if:payout_method,paypal', 'nullable', 'email'],
            'bank_account_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_account_number' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_name' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
            'bank_swift_or_routing' => ['required_if:payout_method,bank_transfer', 'nullable', 'string', 'max:255'],
        ]);

        $details = $data['payout_method'] === 'paypal'
            ? ['paypal_email' => $data['paypal_email']]
            : [
                'account_name' => $data['bank_account_name'],
                'account_number' => $data['bank_account_number'],
                'bank_name' => $data['bank_name'],
                'swift_or_routing' => $data['bank_swift_or_routing'],
            ];

        $request->user()->update([
            'payout_method' => $data['payout_method'],
            'payout_details' => $details,
        ]);

        return back()->with('success', 'Payout details saved.');
    }

    public function requestPayout(Request $request, ReferralPayoutService $service): RedirectResponse
    {
        $currency = $request->string('currency')->upper()->value() ?: null;

        try {
            $service->requestPayout($request->user(), $currency);
        } catch (InvalidArgumentException|RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payout requested — you\'ll be notified once it\'s processed.');
    }
}
