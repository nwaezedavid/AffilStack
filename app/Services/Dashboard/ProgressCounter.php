<?php

namespace App\Services\Dashboard;

use App\Models\CrmEmailSend;
use App\Models\Earning;
use App\Models\Generation;
use App\Models\TrackedLink;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * "What AffilStack has done for you" on the dashboard: running totals of
 * the work AffilStack has done for this account (products researched,
 * content created, prospects found, emails sent, clicks and sales tracked,
 * time saved), each with this week's increase, plus the next milestone.
 * Cached for ten minutes per account.
 */
class ProgressCounter
{
    /**
     * Rough minutes a marketer would spend doing each piece by hand — used
     * only for the "hours saved" estimate, and labelled as an estimate.
     */
    public const MINUTES_SAVED = [
        'research' => 90,
        'blog_article' => 120,
        'youtube_script' => 90,
        'youtube_metadata' => 20,
        'ugc_angles' => 30,
        'ugc_content' => 40,
        'ugc_video' => 180,
        'linkedin_post' => 25,
        'linkedin_article' => 90,
        'linkedin_dm' => 20,
        'linkedin_keywords' => 20,
        'x_thread' => 30,
        'tiktok_video' => 60,
        'pinterest_pins' => 30,
        'email_nurture' => 60,
        'competitor_angles' => 45,
    ];

    public const DEFAULT_MINUTES = 20;

    public const MILESTONES = [10, 25, 50, 100, 250, 500, 1000, 2500, 5000, 10000, 25000, 50000, 100000];

    /**
     * @return array{
     *     tiles: array<int, array{key: string, label: string, value: string, raw: int, week: int, icon: string, tone: string}>,
     *     total: int,
     *     milestone: array{next: int, previous: int, percent: int}
     * }
     */
    public function for(User $user): array
    {
        return Cache::remember("dashboard:progress:{$user->id}", now()->addMinutes(10), fn (): array => $this->compute($user));
    }

    public static function forget(User $user): void
    {
        Cache::forget("dashboard:progress:{$user->id}");
    }

    /**
     * @return array<string, mixed>
     */
    protected function compute(User $user): array
    {
        $weekAgo = now()->subWeek();

        $generations = Generation::query()->where('user_id', $user->id)->where('status', 'completed');
        $content = (clone $generations)->where('module', '!=', 'research');
        $sentEmails = CrmEmailSend::query()->where('user_id', $user->id)->whereNotNull('sent_at');
        $earnings = Earning::query()->where('user_id', $user->id);

        $minutes = (clone $generations)->selectRaw('module, count(*) as total')->groupBy('module')->pluck('total', 'module')
            ->reduce(fn (int $carry, $count, $module): int => $carry + ((int) $count * (self::MINUTES_SAVED[$module] ?? self::DEFAULT_MINUTES)), 0);
        $minutesThisWeek = (clone $generations)->where('created_at', '>=', $weekAgo)->selectRaw('module, count(*) as total')->groupBy('module')->pluck('total', 'module')
            ->reduce(fn (int $carry, $count, $module): int => $carry + ((int) $count * (self::MINUTES_SAVED[$module] ?? self::DEFAULT_MINUTES)), 0);

        $counts = [
            'products' => [$user->visibleOffers()->count(), $user->visibleOffers()->where('offers.created_at', '>=', $weekAgo)->count()],
            'content' => [(clone $content)->count(), (clone $content)->where('created_at', '>=', $weekAgo)->count()],
            'prospects' => [$user->crmContacts()->count(), $user->crmContacts()->where('crm_contacts.created_at', '>=', $weekAgo)->count()],
            'emails' => [(clone $sentEmails)->count(), (clone $sentEmails)->where('sent_at', '>=', $weekAgo)->count()],
            'clicks' => [(int) TrackedLink::query()->where('user_id', $user->id)->sum('clicks_count'), $this->clicksSince($user, $weekAgo)],
            'sales' => [(clone $earnings)->count(), (clone $earnings)->where('created_at', '>=', $weekAgo)->count()],
        ];

        $usdCents = (int) (clone $earnings)->sum('usd_cents');
        $hours = intdiv($minutes, 60);

        $tiles = [
            $this->tile('products', 'Products researched', $counts['products'], 'magnifying-glass-circle', 'violet'),
            $this->tile('content', 'Content pieces created', $counts['content'], 'document-text', 'brand'),
            $this->tile('prospects', 'Prospects found', $counts['prospects'], 'users', 'emerald'),
            $this->tile('emails', 'Emails sent for you', $counts['emails'], 'paper-airplane', 'brand'),
            $this->tile('clicks', 'Link clicks tracked', $counts['clicks'], 'cursor-arrow-rays', 'gold'),
            $this->tile('sales', 'Sales tracked', $counts['sales'], 'banknotes', 'rose', $usdCents > 0 ? '$'.number_format($usdCents / 100, 0).' in commissions' : null),
            [
                'key' => 'hours',
                'label' => 'Hours of work saved (estimate)',
                'value' => number_format($hours),
                'raw' => $hours,
                'week' => intdiv($minutesThisWeek, 60),
                'icon' => 'clock',
                'tone' => 'gold',
                'note' => null,
            ],
        ];

        $total = collect($counts)->sum(fn (array $pair): int => $pair[0]);

        return ['tiles' => $tiles, 'total' => $total, 'milestone' => $this->milestone($total)];
    }

    /**
     * @param  array{0: int, 1: int}  $pair
     * @return array<string, mixed>
     */
    protected function tile(string $key, string $label, array $pair, string $icon, string $tone, ?string $note = null): array
    {
        return ['key' => $key, 'label' => $label, 'value' => number_format($pair[0]), 'raw' => $pair[0], 'week' => $pair[1], 'icon' => $icon, 'tone' => $tone, 'note' => $note];
    }

    protected function clicksSince(User $user, Carbon $since): int
    {
        return (int) TrackedLink::query()
            ->where('tracked_links.user_id', $user->id)
            ->join('link_clicks', 'link_clicks.tracked_link_id', '=', 'tracked_links.id')
            ->where('link_clicks.clicked_at', '>=', $since)
            ->count();
    }

    /**
     * @return array{next: int, previous: int, percent: int}
     */
    protected function milestone(int $total): array
    {
        $previous = 0;

        foreach (self::MILESTONES as $milestone) {
            if ($total < $milestone) {
                return ['next' => $milestone, 'previous' => $previous, 'percent' => (int) floor(($total - $previous) / ($milestone - $previous) * 100)];
            }

            $previous = $milestone;
        }

        return ['next' => $previous, 'previous' => $previous, 'percent' => 100];
    }
}
