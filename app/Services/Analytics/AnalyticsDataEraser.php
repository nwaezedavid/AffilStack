<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsEvent;
use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSession;
use App\Models\AnalyticsVisitor;
use App\Models\ConsentRecord;
use App\Models\PageView;
use App\Models\User;
use App\Support\Analytics\ConsentSettings;
use Illuminate\Support\Carbon;

/**
 * Agent 04: deletion — a user's analytics data when their account is
 * permanently deleted, and everything older than the retention window
 * (Analytics → Cookie Consent, default 13 months) via the scheduled
 * analytics:purge command. Page views are anonymised (ids nulled) rather
 * than deleted, so historic traffic totals stay intact without anything
 * that points back to a person.
 */
class AnalyticsDataEraser
{
    public function eraseUser(int $userId): void
    {
        $visitorIds = AnalyticsVisitor::query()->where('user_id', $userId)->pluck('id');

        PageView::query()
            ->where(fn ($query) => $query->where('user_id', $userId)->orWhereIn('visitor_id', $visitorIds))
            ->update(['user_id' => null, 'visitor_id' => null, 'analytics_session_id' => null, 'session_id' => null, 'scroll_depth' => null]);

        AnalyticsProfile::query()->where('user_id', $userId)->orWhereIn('visitor_id', $visitorIds)->delete();
        AnalyticsEvent::query()->where('user_id', $userId)->orWhereIn('visitor_id', $visitorIds)->delete();
        AnalyticsSession::query()->where('user_id', $userId)->orWhereIn('visitor_id', $visitorIds)->delete();
        ConsentRecord::query()->where('user_id', $userId)->update(['user_id' => null, 'visitor_id' => null]);
        AnalyticsVisitor::query()->whereIn('id', $visitorIds)->delete();
    }

    public function eraseForUser(User $user): void
    {
        $this->eraseUser((int) $user->getKey());
    }

    /**
     * @return array<string, int> rows removed/anonymised per table
     */
    public function purgeExpired(?Carbon $cutoff = null): array
    {
        $cutoff ??= now()->subMonths(ConsentSettings::retentionMonths());
        $counts = [];

        $counts['events'] = $this->chunkedDelete(AnalyticsEvent::query()->where('created_at', '<', $cutoff));
        $counts['page_views'] = $this->chunkedDelete(PageView::query()->where('created_at', '<', $cutoff));
        $counts['sessions'] = $this->chunkedDelete(AnalyticsSession::query()->where('last_activity_at', '<', $cutoff));
        $counts['profiles'] = $this->chunkedDelete(AnalyticsProfile::query()->whereNotNull('visitor_id')->where('last_seen_at', '<', $cutoff));
        $counts['visitors'] = $this->chunkedDelete(AnalyticsVisitor::query()->where(fn ($q) => $q->where('last_seen_at', '<', $cutoff)->orWhereNull('last_seen_at')));
        $counts['consent_records'] = $this->chunkedDelete(ConsentRecord::query()->where('created_at', '<', $cutoff));

        return $counts;
    }

    /**
     * Deletes in batches of 1,000 ids so a large purge never holds a long
     * table lock on shared-hosting MariaDB.
     */
    protected function chunkedDelete($query): int
    {
        $total = 0;

        do {
            $ids = (clone $query)->limit(1000)->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $total += $query->getModel()->newQuery()->whereIn('id', $ids)->delete();
        } while ($ids->count() === 1000);

        return $total;
    }
}
