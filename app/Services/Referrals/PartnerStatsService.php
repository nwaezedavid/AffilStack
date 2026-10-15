<?php

namespace App\Services\Referrals;

use App\Models\Referral;
use App\Models\ReferralEvent;
use App\Models\User;
use App\Support\ReferralProgram;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * The numbers behind the Partner Portal overview: clicks, sign-ups,
 * paying customers and earnings over a chosen period, compared with the
 * period before it, bucketed for the charts, and split by campaign and
 * channel. Earnings in other currencies (naira from Paystack payments)
 * are converted to US dollars so they can be added up.
 */
class PartnerStatsService
{
    /**
     * @var array<string, array{label: string, days: int}>
     */
    public const RANGES = [
        '7d' => ['label' => '7 days', 'days' => 7],
        '30d' => ['label' => '30 days', 'days' => 30],
        '90d' => ['label' => '90 days', 'days' => 90],
        '12m' => ['label' => '12 months', 'days' => 365],
    ];

    public static function normalizeRange(?string $range): string
    {
        return array_key_exists((string) $range, self::RANGES) ? (string) $range : '30d';
    }

    /**
     * @return array{
     *     range: string,
     *     start: CarbonImmutable,
     *     end: CarbonImmutable,
     *     labels: array<int, string>,
     *     series: array{clicks: array<int, int>, signups: array<int, int>, paying: array<int, int>, earnings: array<int, int>},
     *     totals: array{clicks: int, signups: int, paying: int, earnings: int},
     *     previous: array{clicks: int, signups: int, paying: int, earnings: int},
     *     rates: array{signup: float|null, paying: float|null},
     *     campaigns: Collection<int, array<string, mixed>>,
     *     channels: array<string, int>,
     * }
     */
    public function report(User $user, string $range): array
    {
        $range = static::normalizeRange($range);
        $monthly = $range === '12m';
        $end = CarbonImmutable::now()->endOfDay();
        $start = $monthly
            ? $end->subMonthsNoOverflow(11)->startOfMonth()
            : $end->subDays(self::RANGES[$range]['days'] - 1)->startOfDay();
        $previousStart = $monthly ? $start->subYear() : $start->subDays(self::RANGES[$range]['days']);

        $clicks = $user->referralClicks()
            ->where('clicked_at', '>=', $previousStart)
            ->get(['clicked_at', 'source', 'referral_campaign_id']);

        $referrals = $user->referrals()
            ->where(fn ($q) => $q->where('created_at', '>=', $previousStart)->orWhere('converted_at', '>=', $previousStart))
            ->get(['id', 'created_at', 'converted_at', 'referral_campaign_id']);

        $events = ReferralEvent::query()
            ->whereHas('referral', fn ($q) => $q->where('referrer_id', $user->id))
            ->whereNotIn('status', ['reversed', 'rejected'])
            ->where('occurred_at', '>=', $previousStart)
            ->with('referral:id,referral_campaign_id')
            ->get(['id', 'referral_id', 'amount_cents', 'currency', 'occurred_at']);

        $bucket = fn ($date): string => $monthly ? $date->format('Y-m') : $date->format('Y-m-d');
        $keys = $monthly
            ? collect(CarbonPeriod::create($start, '1 month', $end))->map(fn ($d) => $d->format('Y-m'))->values()->all()
            : collect(CarbonPeriod::create($start, '1 day', $end))->map(fn ($d) => $d->format('Y-m-d'))->values()->all();
        $labels = array_map(fn (string $key) => $monthly
            ? CarbonImmutable::createFromFormat('Y-m', $key)->format('M Y')
            : CarbonImmutable::createFromFormat('Y-m-d', $key)->format('M j'), $keys);

        $inPeriod = fn ($date) => $date !== null && $date->gte($start) && $date->lte($end);
        $inPrevious = fn ($date) => $date !== null && $date->gte($previousStart) && $date->lt($start);
        $usd = fn ($event): int => ReferralProgram::toUsdCents((int) $event->amount_cents, (string) $event->currency);

        $series = ['clicks' => [], 'signups' => [], 'paying' => [], 'earnings' => []];
        $empty = array_fill_keys($keys, 0);
        $series['clicks'] = $empty;
        $series['signups'] = $empty;
        $series['paying'] = $empty;
        $series['earnings'] = $empty;

        foreach ($clicks as $click) {
            if ($inPeriod($click->clicked_at)) {
                $series['clicks'][$bucket($click->clicked_at)]++;
            }
        }

        foreach ($referrals as $referral) {
            if ($inPeriod($referral->created_at)) {
                $series['signups'][$bucket($referral->created_at)]++;
            }

            if ($inPeriod($referral->converted_at)) {
                $series['paying'][$bucket($referral->converted_at)]++;
            }
        }

        foreach ($events as $event) {
            if ($inPeriod($event->occurred_at)) {
                $series['earnings'][$bucket($event->occurred_at)] += $usd($event);
            }
        }

        $totals = array_map(fn (array $values): int => array_sum($values), $series);
        $previous = [
            'clicks' => $clicks->filter(fn ($c) => $inPrevious($c->clicked_at))->count(),
            'signups' => $referrals->filter(fn ($r) => $inPrevious($r->created_at))->count(),
            'paying' => $referrals->filter(fn ($r) => $inPrevious($r->converted_at))->count(),
            'earnings' => (int) $events->filter(fn ($e) => $inPrevious($e->occurred_at))->sum($usd),
        ];

        $periodClicks = $clicks->filter(fn ($c) => $inPeriod($c->clicked_at));
        $periodReferrals = $referrals->filter(fn ($r) => $inPeriod($r->created_at));
        $periodPaying = $referrals->filter(fn ($r) => $inPeriod($r->converted_at));
        $periodEvents = $events->filter(fn ($e) => $inPeriod($e->occurred_at));

        $campaigns = $user->referralCampaigns()->orderBy('name')->get()->map(fn ($campaign): array => [
            'campaign' => $campaign,
            'clicks' => $periodClicks->where('referral_campaign_id', $campaign->id)->count(),
            'signups' => $periodReferrals->where('referral_campaign_id', $campaign->id)->count(),
            'paying' => $periodPaying->where('referral_campaign_id', $campaign->id)->count(),
            'earnings' => (int) $periodEvents->filter(fn ($e) => $e->referral?->referral_campaign_id === $campaign->id)->sum($usd),
        ])->sortByDesc(fn (array $row) => $row['earnings'] * 1000 + $row['paying'] * 100 + $row['clicks'])->values();

        $channels = $periodClicks->groupBy(fn ($c) => $c->source ?: 'link')->map->count()->sortDesc()->all();

        return [
            'range' => $range,
            'start' => $start,
            'end' => $end,
            'labels' => $labels,
            'series' => array_map('array_values', $series),
            'totals' => $totals,
            'previous' => $previous,
            'rates' => [
                'signup' => $totals['clicks'] > 0 ? round($totals['signups'] / $totals['clicks'] * 100, 1) : null,
                'paying' => $totals['signups'] > 0 ? round($totals['paying'] / $totals['signups'] * 100, 1) : null,
            ],
            'campaigns' => $campaigns,
            'channels' => $channels,
        ];
    }

    /**
     * Lifetime balances in US dollars: pending review, approved (ready to
     * request) and paid out.
     *
     * @return array{pending: int, approved: int, paid: int}
     */
    public function balances(User $user): array
    {
        $events = ReferralEvent::query()
            ->whereHas('referral', fn ($q) => $q->where('referrer_id', $user->id))
            ->whereIn('status', ['pending', 'approved', 'paid'])
            ->get(['amount_cents', 'currency', 'status']);

        $sum = fn (string $status): int => (int) $events->where('status', $status)
            ->sum(fn ($e) => ReferralProgram::toUsdCents((int) $e->amount_cents, (string) $e->currency));

        return ['pending' => $sum('pending'), 'approved' => $sum('approved'), 'paid' => $sum('paid')];
    }

    /**
     * Percentage change from the previous period, or null when there is
     * nothing to compare with.
     */
    public static function change(int $current, int $previous): ?float
    {
        if ($previous === 0) {
            return null;
        }

        return round(($current - $previous) / $previous * 100, 1);
    }

    /**
     * @return Collection<int, Referral>
     */
    public function recentReferrals(User $user, int $limit = 50): Collection
    {
        return $user->referrals()->with(['referredUser:id,name,email', 'events', 'campaign:id,name'])->latest()->limit($limit)->get();
    }
}
