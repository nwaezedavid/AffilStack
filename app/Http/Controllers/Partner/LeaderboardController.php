<?php

namespace App\Http\Controllers\Partner;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Support\ReferralProgram;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * This month's top 10 partners, ranked by new paying customers (earnings
 * break ties). Names are shown as "First L." and only for partners who
 * kept "Show me on the leaderboard" on; amounts are never shown. Turned
 * on or off by the admin in Partners → Affiliate Program.
 */
class LeaderboardController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(ReferralProgram::leaderboardEnabled(), 404);

        $user = $request->user();
        $month = CarbonImmutable::now()->startOfMonth();

        $partners = User::query()
            ->where('is_affiliate_only', true)
            ->where('is_suspended', false)
            ->get(['id', 'name', 'show_on_partner_leaderboard']);

        $paying = Referral::query()
            ->whereIn('referrer_id', $partners->pluck('id'))
            ->where('converted_at', '>=', $month)
            ->selectRaw('referrer_id, count(*) as total')
            ->groupBy('referrer_id')
            ->pluck('total', 'referrer_id');

        $earnings = ReferralEvent::query()
            ->join('referrals', 'referrals.id', '=', 'referral_events.referral_id')
            ->whereIn('referrals.referrer_id', $partners->pluck('id'))
            ->whereNotIn('referral_events.status', ['reversed', 'rejected'])
            ->where('referral_events.occurred_at', '>=', $month)
            ->get(['referrals.referrer_id', 'referral_events.amount_cents', 'referral_events.currency'])
            ->groupBy('referrer_id')
            ->map(fn ($rows) => $rows->sum(fn ($row) => ReferralProgram::toUsdCents((int) $row->amount_cents, (string) $row->currency)));

        $ranked = $partners
            ->map(fn (User $partner) => [
                'user' => $partner,
                'paying' => (int) ($paying[$partner->id] ?? 0),
                'earnings' => (int) ($earnings[$partner->id] ?? 0),
            ])
            ->filter(fn (array $row) => $row['paying'] > 0 || $row['earnings'] > 0)
            ->sortBy([['paying', 'desc'], ['earnings', 'desc']])
            ->values();

        $myPosition = $ranked->search(fn (array $row) => $row['user']->id === $user->id);

        $board = $ranked
            ->filter(fn (array $row) => $row['user']->show_on_partner_leaderboard || $row['user']->id === $user->id)
            ->take(10)
            ->map(fn (array $row) => $row + ['rank' => $ranked->search($row) + 1, 'display' => static::displayName($row['user']->name)])
            ->values();

        return view('partner.leaderboard', [
            'user' => $user,
            'board' => $board,
            'month' => $month,
            'myRank' => $myPosition === false ? null : $myPosition + 1,
            'myPaying' => $myPosition === false ? 0 : $ranked[$myPosition]['paying'],
        ]);
    }

    public static function displayName(string $name): string
    {
        $parts = preg_split('/\s+/', trim($name)) ?: [];
        $first = Str::ucfirst(Str::lower($parts[0] ?? 'Partner'));
        $last = count($parts) > 1 ? ' '.Str::upper(mb_substr(end($parts), 0, 1)).'.' : '';

        return $first.$last;
    }
}
