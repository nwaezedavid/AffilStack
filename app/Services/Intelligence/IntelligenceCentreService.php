<?php

namespace App\Services\Intelligence;

use App\Models\IntelligenceCentreReport;
use App\Models\Plan;
use App\Models\User;
use App\Services\AI\AIGenerationException;
use App\Services\AI\AIProvider;
use App\Services\Credits\CreditManager;
use App\Services\Credits\InsufficientCreditsException;

/**
 * Task #7 ("Intelligence Centre"): a self-assessment dashboard feature —
 * the AI reviews a snapshot of the account's own activity (offers,
 * generation success rate, credit usage, channel usage vs. what the plan
 * unlocks, CRM/referral/earnings activity) and produces a health score,
 * concrete next steps, and a plan-upgrade recommendation when the numbers
 * actually warrant one. Synchronous, credit-gated, same pattern as
 * LinkedInReplyAssistantService — the user clicks "Run assessment" and
 * waits for the result rather than this running in the background.
 */
class IntelligenceCentreService
{
    /**
     * Generation module => the plan channel it belongs to (Plan::channels).
     * Anything not listed here (email_nurture, competitor_angles,
     * localization) isn't a plan-gated channel — see PlansSeeder.
     *
     * @var array<string, string>
     */
    protected const MODULE_CHANNELS = [
        'research' => 'research',
        'blog_article' => 'blog',
        'linkedin_keywords' => 'linkedin',
        'linkedin_dm_sequence' => 'linkedin',
        'linkedin_post' => 'linkedin',
        'linkedin_article' => 'linkedin',
        'youtube_script' => 'youtube',
        'youtube_metadata' => 'youtube',
        'ugc_angles' => 'ugc',
        'ugc_content' => 'ugc',
        'ugc_video' => 'ugc',
        'x_thread' => 'x',
        'tiktok_video' => 'tiktok',
        'pinterest_pin' => 'pinterest',
    ];

    public function __construct(protected AIProvider $ai, protected CreditManager $credits) {}

    /**
     * @throws InsufficientCreditsException
     * @throws AIGenerationException
     */
    public function generate(User $user): IntelligenceCentreReport
    {
        $cost = (int) config('credits.costs.intelligence_centre');

        if (! $this->credits->hasEnough($user, $cost)) {
            throw new InsufficientCreditsException("Need {$cost} credits to run an Intelligence Centre assessment.");
        }

        $metrics = $this->buildMetrics($user);
        $assessment = $this->assess($metrics);

        $report = IntelligenceCentreReport::updateOrCreate(
            ['user_id' => $user->id],
            ['metrics' => $metrics, 'assessment' => $assessment, 'generated_at' => now()]
        );

        $this->credits->spend($user, $cost, 'intelligence_centre', $report);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildMetrics(User $user): array
    {
        $subscription = $user->activeSubscription;
        $plan = $subscription?->plan;

        $offers = $user->offers()->get(['id', 'status']);
        $generations = $user->generations()->get(['id', 'module', 'status']);

        $completed = $generations->where('status', 'completed')->count();
        $failed = $generations->where('status', 'failed')->count();
        $attempted = $completed + $failed;

        $moduleCounts = $generations->countBy('module');
        $usedChannels = $moduleCounts->keys()
            ->map(fn (string $module) => self::MODULE_CHANNELS[$module] ?? null)
            ->filter()
            ->unique()
            ->values();

        $contactsCount = $user->crmContacts()->count();
        if ($contactsCount > 0 && in_array('google_maps', $plan?->channels ?? [], true)) {
            $usedChannels->push('google_maps');
        }

        $availableChannels = $plan?->channels ?? [];
        $unusedChannels = collect($availableChannels)->diff($usedChannels)->values();

        $offersLimit = (int) ($plan?->active_products_limit ?? 0);
        $contactsLimit = (int) ($plan?->contact_limit ?? 0);
        $seatsLimit = (int) ($plan?->team_seats ?? 1);

        return [
            'account_age_days' => (int) $user->created_at->diffInDays(now()),
            'plan' => $plan ? [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'sort_order' => $plan->sort_order,
                'is_trialing' => $subscription?->status === 'trialing',
            ] : null,
            'credits' => [
                'balance' => $user->credits_balance,
                'monthly_grant' => $plan?->credits_per_month ?? 0,
                'percent_remaining' => $plan?->credits_per_month
                    ? (int) round(($user->credits_balance / $plan->credits_per_month) * 100)
                    : null,
            ],
            'offers' => [
                'total' => $offers->count(),
                'ready' => $offers->where('status', 'ready')->count(),
                'researching' => $offers->where('status', 'researching')->count(),
                'archived' => $offers->where('status', 'archived')->count(),
                'limit' => $offersLimit,
                'at_limit' => $offersLimit > 0 && $offers->whereNotIn('status', ['archived'])->count() >= $offersLimit,
            ],
            'generations' => [
                'total' => $generations->count(),
                'completed' => $completed,
                'failed' => $failed,
                'success_rate_percent' => $attempted > 0 ? (int) round(($completed / $attempted) * 100) : null,
                'most_used_modules' => $moduleCounts->sortDesc()->take(3)->keys()->values()->all(),
            ],
            'channels' => [
                'available_on_plan' => array_values($availableChannels),
                'used' => $usedChannels->all(),
                'unused' => $unusedChannels->all(),
            ],
            'crm' => [
                'contacts' => $contactsCount,
                'limit' => $contactsLimit,
                'at_limit' => $contactsLimit > 0 && $contactsCount >= $contactsLimit,
                'email_sending_connected' => (bool) $user->emailConnection?->isVerified(),
            ],
            'team' => [
                'seats_used' => $user->seats()->count(),
                'seats_included' => $seatsLimit,
            ],
            'social_connections' => $user->socialConnections()->pluck('provider')->all(),
            'referrals_and_earnings' => [
                'referral_count' => $user->referrals()->count(),
                'total_earnings_cents' => (int) $user->earnings()->sum('amount_cents'),
                'unpaid_commission_cents' => $user->unpaidApprovedCommissionCents(),
            ],
            'candidate_upgrade_plans' => $plan
                ? Plan::where('is_active', true)->where('sort_order', '>', $plan->sort_order)->orderBy('sort_order')
                    ->get(['name', 'slug', 'price_monthly_cents', 'credits_per_month', 'active_products_limit', 'contact_limit', 'team_seats', 'channels'])
                    ->toArray()
                : Plan::where('is_active', true)->orderBy('sort_order')
                    ->get(['name', 'slug', 'price_monthly_cents', 'credits_per_month', 'active_products_limit', 'contact_limit', 'team_seats', 'channels'])
                    ->toArray(),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<string, mixed>
     */
    protected function assess(array $metrics): array
    {
        $system = <<<'PROMPT'
            You are an account-health analyst for AffilStack, an all-in-one
            affiliate marketing SaaS. You are given a JSON snapshot of one
            user's account: their plan, credit usage, offers, AI generation
            success rate, which content channels their plan unlocks vs. which
            they actually use, CRM/team/referral activity, and a list of plans
            priced above their current one (empty if they're already on the
            top plan or have no active plan at all).

            Write a short, honest, encouraging self-assessment — never
            generic filler, always grounded in the specific numbers given.

            Return a JSON object with exactly these keys:
            - "health_score": integer 0-100, an overall account-health score.
              Weigh generation success rate, whether they're actively using
              channels their plan already unlocks, and whether they're
              running low on credits or hitting plan limits.
            - "headline": one sentence summarizing where they stand right now.
            - "strengths": 1-4 short strings, specific things going well.
            - "gaps": 1-4 short strings, specific things holding them back
              (e.g. unused channels already included in their plan, a low
              generation success rate, no email-sending connected).
            - "next_steps": 3-5 short, concrete, ordered actions specific to
              this account's numbers — never vague advice like "post more".
            - "upgrade_recommended": boolean. Only true if the numbers
              genuinely justify it — e.g. they are at or near a real plan
              limit (offers/contacts/seats), or they'd meaningfully benefit
              from a channel only a higher plan unlocks, or they are
              consistently near-zero on credits before their next grant.
              Never recommend an upgrade just because a higher plan exists.
            - "upgrade_reason": a one-sentence reason if upgrade_recommended
              is true, otherwise null.
            - "suggested_plan_slug": the "slug" of one plan from
              candidate_upgrade_plans if upgrade_recommended is true,
              otherwise null. Must be one of the given candidates verbatim.
            PROMPT;

        $result = $this->ai->generateJson($system, json_encode($metrics));

        return [
            'health_score' => max(0, min(100, (int) ($result['health_score'] ?? 0))),
            'headline' => (string) ($result['headline'] ?? ''),
            'strengths' => array_values((array) ($result['strengths'] ?? [])),
            'gaps' => array_values((array) ($result['gaps'] ?? [])),
            'next_steps' => array_values((array) ($result['next_steps'] ?? [])),
            'upgrade_recommended' => (bool) ($result['upgrade_recommended'] ?? false),
            'upgrade_reason' => $result['upgrade_reason'] ?? null,
            'suggested_plan_slug' => $result['suggested_plan_slug'] ?? null,
        ];
    }
}
