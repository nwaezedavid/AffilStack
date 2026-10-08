<?php

namespace App\Services\Analytics;

use App\Models\AnalyticsProfile;
use App\Models\AnalyticsSegment;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Agent 04: turns an advertising segment's rules into a query over
 * analytics_profiles. Every rule must match (AND). Anything that changes
 * from minute to minute — subscriptions, payments, inactivity, the
 * person's CURRENT marketing consent — is checked live against the source
 * tables, so counts and exports are never stale on the facts that matter.
 *
 * Rule shape: ['field' => string, 'operator' => string, 'value' => mixed, 'days' => ?int]
 */
class SegmentEvaluator
{
    /**
     * The rule fields an admin can build segments from, with the operators
     * each supports. Used by the segment form and validated here.
     *
     * @var array<string, array{label: string, operators: array<int, string>, help: string}>
     */
    public const FIELDS = [
        'lifecycle_stage' => ['label' => 'Lifecycle stage', 'operators' => ['in', 'not_in'], 'help' => 'One or more stages, e.g. pricing_viewer, checkout_started.'],
        'did_event' => ['label' => 'Did an event (within N days)', 'operators' => ['is'], 'help' => 'Event name, e.g. pricing_viewed, checkout_started, cta_click.'],
        'not_did_event' => ['label' => 'Did NOT do an event (within N days)', 'operators' => ['is'], 'help' => 'Event name.'],
        'has_active_subscription' => ['label' => 'Has an active subscription', 'operators' => ['is'], 'help' => 'Yes / no.'],
        'is_customer' => ['label' => 'Has ever paid', 'operators' => ['is'], 'help' => 'Yes / no.'],
        'engagement_score' => ['label' => 'Engagement score', 'operators' => ['gte', 'lte'], 'help' => '0–100.'],
        'inactive_days' => ['label' => 'Days since last seen', 'operators' => ['gte', 'lte'], 'help' => 'Uses dashboard activity for customers.'],
        'country' => ['label' => 'Country', 'operators' => ['in', 'not_in'], 'help' => 'ISO codes, e.g. NG, GH, KE, GB, US.'],
        'first_touch_channel' => ['label' => 'First-touch channel', 'operators' => ['in', 'not_in'], 'help' => 'Direct, Organic Search, Social, Paid, Email, Referral…'],
        'last_touch_source' => ['label' => 'Last-touch source', 'operators' => ['is', 'contains'], 'help' => 'utm_source or referring site, e.g. facebook, google.'],
        'campaign' => ['label' => 'Campaign (first or last touch)', 'operators' => ['is', 'contains'], 'help' => 'utm_campaign value.'],
        'used_module' => ['label' => 'Generated content with module', 'operators' => ['is'], 'help' => 'e.g. blog_article, ugc_video, email_nurture.'],
        'affiliate_network' => ['label' => 'Researched products on network', 'operators' => ['is'], 'help' => 'e.g. ClickBank, Digistore24, Selar.'],
        'identified' => ['label' => 'Is a registered user', 'operators' => ['is'], 'help' => 'Yes / no.'],
    ];

    /**
     * @return Builder<AnalyticsProfile>
     */
    public function query(AnalyticsSegment|array $segment): Builder
    {
        $rules = $segment instanceof AnalyticsSegment ? (array) $segment->rules : $segment;
        $query = AnalyticsProfile::query()
            ->where(fn (Builder $q) => $q->whereNull('analytics_profiles.user_id')->orWhereIn('analytics_profiles.user_id', DB::table('users')->whereNull('deleted_at')->select('id')));

        foreach ($rules as $rule) {
            if (is_array($rule) && isset($rule['field'], self::FIELDS[$rule['field']])) {
                $this->apply($query, $rule);
            }
        }

        return $query;
    }

    public function count(AnalyticsSegment|array $segment): int
    {
        return $this->query($segment)->count();
    }

    /**
     * People in the segment who can be uploaded to an ad platform: known by
     * email (a registered user, or a hashed checkout email) AND whose
     * latest cookie choice allows marketing.
     *
     * @return Builder<AnalyticsProfile>
     */
    public function exportableQuery(AnalyticsSegment|array $segment): Builder
    {
        return $this->query($segment)
            ->where(function (Builder $q) {
                $q->whereNotNull('analytics_profiles.user_id')
                    ->orWhereIn('analytics_profiles.visitor_id', DB::table('analytics_visitors')->whereNotNull('email_sha256')->select('id'));
            })
            ->where(function (Builder $q) {
                $q->where(function (Builder $u) {
                    $u->whereNotNull('analytics_profiles.user_id')
                        ->whereRaw('(select cr.marketing from consent_records cr where cr.user_id = analytics_profiles.user_id order by cr.id desc limit 1) = ?', [1]);
                })->orWhere(function (Builder $v) {
                    $v->whereNull('analytics_profiles.user_id')
                        ->whereRaw('(select cr.marketing from consent_records cr where cr.visitor_id = analytics_profiles.visitor_id order by cr.id desc limit 1) = ?', [1]);
                });
            });
    }

    public function exportableCount(AnalyticsSegment|array $segment): int
    {
        return $this->exportableQuery($segment)->count();
    }

    /**
     * @param  Builder<AnalyticsProfile>  $query
     * @param  array<string, mixed>  $rule
     */
    protected function apply(Builder $query, array $rule): void
    {
        $operator = (string) ($rule['operator'] ?? 'is');
        $value = $rule['value'] ?? null;
        $days = max(1, min(400, (int) ($rule['days'] ?? 30)));
        $list = array_values(array_filter(array_map('trim', is_array($value) ? $value : explode(',', (string) $value)), fn ($v) => $v !== ''));
        $truthy = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        match ($rule['field']) {
            'lifecycle_stage' => $operator === 'not_in'
                ? $query->whereNotIn('lifecycle_stage', $list)
                : $query->whereIn('lifecycle_stage', $list),
            'did_event' => $query->whereExists($this->eventSubquery((string) $value, $days)),
            'not_did_event' => $query->whereNotExists($this->eventSubquery((string) $value, $days)),
            'has_active_subscription' => $truthy
                ? $query->whereIn('analytics_profiles.user_id', $this->activeSubscribers())
                : $query->where(fn (Builder $q) => $q->whereNull('analytics_profiles.user_id')->orWhereNotIn('analytics_profiles.user_id', $this->activeSubscribers())),
            'is_customer' => $truthy
                ? $query->whereIn('analytics_profiles.user_id', $this->everPaid())
                : $query->where(fn (Builder $q) => $q->whereNull('analytics_profiles.user_id')->orWhereNotIn('analytics_profiles.user_id', $this->everPaid())),
            'engagement_score' => $query->where('engagement_score', $operator === 'lte' ? '<=' : '>=', (int) $value),
            'inactive_days' => $this->applyInactivity($query, $operator, (int) $value),
            'country' => $operator === 'not_in'
                ? $query->where(fn (Builder $q) => $q->whereNull('country')->orWhereNotIn('country', array_map('strtoupper', $list)))
                : $query->whereIn('country', array_map('strtoupper', $list)),
            'first_touch_channel' => $operator === 'not_in'
                ? $query->where(fn (Builder $q) => $q->whereNull('ft_channel')->orWhereNotIn('ft_channel', $list))
                : $query->whereIn('ft_channel', $list),
            'last_touch_source' => $operator === 'contains'
                ? $query->where('lt_source', 'like', '%'.$this->escapeLike(strtolower((string) $value)).'%')
                : $query->where('lt_source', strtolower((string) $value)),
            'campaign' => $query->where(function (Builder $q) use ($operator, $value) {
                $needle = strtolower((string) $value);
                $op = $operator === 'contains' ? 'like' : '=';
                $needle = $operator === 'contains' ? '%'.$this->escapeLike($needle).'%' : $needle;
                $q->where('ft_campaign', $op, $needle)->orWhere('lt_campaign', $op, $needle);
            }),
            'used_module' => $query->whereIn('analytics_profiles.user_id', DB::table('generations')->where('module', (string) $value)->where('status', 'completed')->select('user_id')),
            'affiliate_network' => $query->whereIn('analytics_profiles.user_id', DB::table('offers')->where('affiliate_network', (string) $value)->select('user_id')),
            'identified' => $truthy ? $query->whereNotNull('analytics_profiles.user_id') : $query->whereNull('analytics_profiles.user_id'),
        };
    }

    protected function eventSubquery(string $name, int $days): \Closure
    {
        return function (QueryBuilder $sub) use ($name, $days) {
            $sub->selectRaw('1')
                ->from('analytics_events as ae')
                ->where('ae.name', $name)
                ->where('ae.created_at', '>=', now()->subDays($days))
                ->where(function (QueryBuilder $match) {
                    $match->where(fn (QueryBuilder $u) => $u->whereNotNull('analytics_profiles.user_id')->whereColumn('ae.user_id', 'analytics_profiles.user_id'))
                        ->orWhere(fn (QueryBuilder $v) => $v->whereNull('analytics_profiles.user_id')->whereColumn('ae.visitor_id', 'analytics_profiles.visitor_id'));
                });
        };
    }

    /**
     * @param  Builder<AnalyticsProfile>  $query
     */
    protected function applyInactivity(Builder $query, string $operator, int $days): void
    {
        $threshold = now()->subDays(max(0, $days));

        if ($operator === 'lte') {
            $query->where('last_seen_at', '>=', $threshold);

            return;
        }

        $query->where(function (Builder $q) use ($threshold) {
            $q->where(fn (Builder $u) => $u->whereNotNull('analytics_profiles.user_id')
                ->whereIn('analytics_profiles.user_id', DB::table('users')->where(fn ($w) => $w->whereNull('last_active_at')->orWhere('last_active_at', '<', $threshold))->select('id')))
                ->orWhere(fn (Builder $v) => $v->whereNull('analytics_profiles.user_id')->where('last_seen_at', '<', $threshold));
        });
    }

    protected function activeSubscribers(): QueryBuilder
    {
        return DB::table('subscriptions')->where('status', 'active')->select('user_id');
    }

    protected function everPaid(): QueryBuilder
    {
        return DB::table('subscriptions')->select('user_id');
    }

    protected function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * The five preset audiences the owner asked for.
     *
     * @return array<int, array{key: string, name: string, description: string, purpose: string, rules: array<int, array<string, mixed>>}>
     */
    public static function presets(): array
    {
        return [
            [
                'key' => 'pricing_no_purchase_30d',
                'name' => 'Viewed pricing, didn\'t buy (30 days)',
                'description' => 'Looked at the pricing page in the last 30 days but has no paid plan. Retarget with a testimonial or a limited-time offer.',
                'purpose' => 'target',
                'rules' => [
                    ['field' => 'did_event', 'operator' => 'is', 'value' => 'pricing_viewed', 'days' => 30],
                    ['field' => 'is_customer', 'operator' => 'is', 'value' => false],
                ],
            ],
            [
                'key' => 'checkout_abandoned',
                'name' => 'Started checkout, didn\'t pay',
                'description' => 'Began a checkout in the last 30 days and never paid. Your warmest prospects — remind them what they are missing.',
                'purpose' => 'target',
                'rules' => [
                    ['field' => 'did_event', 'operator' => 'is', 'value' => 'checkout_started', 'days' => 30],
                    ['field' => 'is_customer', 'operator' => 'is', 'value' => false],
                ],
            ],
            [
                'key' => 'customers_exclude',
                'name' => 'Customers — exclude from acquisition ads',
                'description' => 'Everyone with an active subscription. Upload as an EXCLUSION list so you stop paying to advertise to people who already bought.',
                'purpose' => 'exclude',
                'rules' => [
                    ['field' => 'has_active_subscription', 'operator' => 'is', 'value' => true],
                ],
            ],
            [
                'key' => 'highly_engaged',
                'name' => 'Highly engaged users',
                'description' => 'Engagement score of '.config('analytics.engagement.highly_engaged_score').'+ (frequent visits, heavy feature use). Great seed audience for lookalikes and for upsell or referral campaigns.',
                'purpose' => 'target',
                'rules' => [
                    ['field' => 'engagement_score', 'operator' => 'gte', 'value' => (int) config('analytics.engagement.highly_engaged_score')],
                ],
            ],
            [
                'key' => 'inactive_customers',
                'name' => 'Inactive customers',
                'description' => 'Paying customers who haven\'t used the dashboard for 14+ days — at risk of cancelling. Win them back with a "here\'s what\'s new" campaign.',
                'purpose' => 'target',
                'rules' => [
                    ['field' => 'has_active_subscription', 'operator' => 'is', 'value' => true],
                    ['field' => 'inactive_days', 'operator' => 'gte', 'value' => 14],
                ],
            ],
        ];
    }

    /**
     * Creates any missing preset (never overwrites an admin's edits).
     */
    public static function ensurePresets(): void
    {
        foreach (self::presets() as $preset) {
            AnalyticsSegment::query()->firstOrCreate(['key' => $preset['key']], $preset + ['is_preset' => true]);
        }
    }
}
