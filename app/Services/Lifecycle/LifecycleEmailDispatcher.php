<?php

namespace App\Services\Lifecycle;

use App\Jobs\SendLifecycleEmail;
use App\Models\LifecycleEmail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Support\Countries;
use App\Support\Lifecycle\FestivalCalendar;
use App\Support\Lifecycle\LifecycleAudience;
use App\Support\Lifecycle\LifecycleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Decides, every hour, who is due a birthday, festive-season or
 * re-engagement email right now (it's their send hour, in their own
 * timezone) and queues exactly one SendLifecycleEmail job per due email.
 * Idempotency comes from claim(): a LifecycleEmailLog row is inserted
 * against a unique (user, email, occurrence) index BEFORE anything is
 * queued, so re-running the command — or a cron that fires twice — never
 * sends anything twice. Called by the lifecycle:* scheduled commands.
 */
class LifecycleEmailDispatcher
{
    /**
     * Roles that are staff, not customers — never nudged to "come back".
     */
    protected const STAFF_ROLES = ['admin', 'super-admin', 'support', 'admin_sub'];

    public function dispatchBirthdays(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();
        $email = LifecycleEmail::ofType(LifecycleEmail::TYPE_BIRTHDAY)->enabled()->orderBy('sort_order')->first();

        if (! $email || ! LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_BIRTHDAY)) {
            return 0;
        }

        $queued = 0;

        // Every timezone on Earth is within a day of UTC, so only
        // birthdays on yesterday/today/tomorrow (UTC) can be "today"
        // locally — plus 29 Feb ones when one of those is a non-leap 28 Feb.
        $query = $this->recipients()->whereNotNull('birth_date')->where(function (Builder $query) use ($now) {
            foreach ([$now->subDay(), $now, $now->addDay()] as $date) {
                $query->orWhere(fn (Builder $q) => $q->whereMonth('birth_date', $date->month)->whereDay('birth_date', $date->day));

                if ($date->month === 2 && $date->day === 28 && ! $date->isLeapYear()) {
                    $query->orWhere(fn (Builder $q) => $q->whereMonth('birth_date', 2)->whereDay('birth_date', 29));
                }
            }
        });

        $query->chunkById(200, function (Collection $users) use ($email, $now, &$queued) {
            foreach ($users as $user) {
                $localNow = LifecycleAudience::localNow($user, $now);

                if (LifecycleAudience::inSendWindow($localNow) && LifecycleAudience::isBirthday($user, $localNow)) {
                    $queued += (int) $this->claim($user, $email, (string) $localNow->year, $localNow);
                }
            }
        });

        return $queued;
    }

    public function dispatchFestive(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        if (! LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_FESTIVE)) {
            return 0;
        }

        $festivals = $this->festivalsInWindow($now);

        if ($festivals->isEmpty()) {
            return 0;
        }

        $queued = 0;
        $query = $this->recipients();

        // When every festival in the window is country-gated (the usual
        // case for national days), only those countries' users can match.
        $gatedCountries = $festivals->every(fn (LifecycleEmail $email) => $email->countryCodes() !== [])
            ? $festivals->flatMap(fn (LifecycleEmail $email) => $email->countryCodes())->unique()->values()
            : null;

        if ($gatedCountries !== null) {
            $names = $gatedCountries->map(fn (string $code) => Countries::name($code))->filter();
            $query->where(fn (Builder $q) => $q->whereIn('country', $gatedCountries->all())->orWhereIn('country', $names->all()));
        }

        $query->chunkById(200, function (Collection $users) use ($festivals, $now, &$queued) {
            foreach ($users as $user) {
                $queued += (int) $this->dispatchFestiveFor($user, $festivals, $now);
            }
        });

        return $queued;
    }

    /**
     * @param  Collection<int, LifecycleEmail>  $festivals
     */
    public function dispatchFestiveFor(User $user, Collection $festivals, CarbonImmutable $now): bool
    {
        $localNow = LifecycleAudience::localNow($user, $now);

        if (! LifecycleAudience::inSendWindow($localNow)) {
            return false;
        }

        $alreadyHadOneToday = LifecycleEmailLog::query()
            ->where('user_id', $user->id)
            ->where('type', LifecycleEmail::TYPE_FESTIVE)
            ->whereDate('local_date', $localNow->toDateString())
            ->exists();

        if ($alreadyHadOneToday) {
            return false;
        }

        $country = Countries::normalize($user->country);

        $best = $festivals
            ->filter(fn (LifecycleEmail $email) => LifecycleAudience::festiveEligible($email, $user))
            ->map(fn (LifecycleEmail $email) => [$email, FestivalCalendar::occurrenceForSendDate($email, $localNow, $country)])
            ->filter(fn (array $pair) => $pair[1] !== null)
            ->sort(fn (array $a, array $b) => [$b[0]->priority(), $a[0]->sort_order] <=> [$a[0]->priority(), $b[0]->sort_order])
            ->first();

        if (! $best) {
            return false;
        }

        [$email, $festivalDate] = $best;

        return $this->claim($user, $email, $festivalDate->toDateString(), $localNow);
    }

    public function dispatchReengagement(?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        if (! LifecycleSettings::campaignEnabled(LifecycleEmail::TYPE_REENGAGEMENT)) {
            return 0;
        }

        $steps = LifecycleEmail::ofType(LifecycleEmail::TYPE_REENGAGEMENT)
            ->enabled()
            ->whereNotNull('inactive_days')
            ->where('inactive_days', '>', 0)
            ->orderBy('inactive_days')
            ->get();

        if ($steps->isEmpty()) {
            return 0;
        }

        $queued = 0;
        $cutoff = $now->subDays($steps->first()->inactive_days);

        $this->reengagementCandidates()
            ->whereRaw('COALESCE(last_active_at, last_login_at, created_at) <= ?', [$cutoff->toDateTimeString()])
            ->chunkById(200, function (Collection $users) use ($steps, $now, &$queued) {
                foreach ($users as $user) {
                    $queued += (int) $this->dispatchReengagementFor($user, $steps, $now);
                }
            });

        return $queued;
    }

    /**
     * Sends the furthest step the user has reached in this inactivity
     * spell, once — earlier steps they skipped past (e.g. the cron was off,
     * or they were already 40 days quiet when this launched) are logged as
     * skipped so a long-dormant user gets one email, not three at once.
     *
     * @param  Collection<int, LifecycleEmail>  $steps  ordered by inactive_days
     */
    public function dispatchReengagementFor(User $user, Collection $steps, CarbonImmutable $now): bool
    {
        $localNow = LifecycleAudience::localNow($user, $now);

        if (! LifecycleAudience::inSendWindow($localNow)) {
            return false;
        }

        $anchor = self::inactivityAnchor($user);
        $daysInactive = (int) floor($anchor->diffInDays($now, true));
        $reached = $steps->filter(fn (LifecycleEmail $step) => $step->inactive_days <= $daysInactive)->values();

        if ($reached->isEmpty()) {
            return false;
        }

        $occurrence = self::spellOccurrence($user);
        $current = $reached->last();

        if (LifecycleEmailLog::where('user_id', $user->id)->where('lifecycle_email_id', $current->id)->where('occurrence', $occurrence)->exists()) {
            return false;
        }

        foreach ($reached->slice(0, -1) as $skipped) {
            LifecycleEmailLog::query()->insertOrIgnore([
                'user_id' => $user->id,
                'lifecycle_email_id' => $skipped->id,
                'type' => LifecycleEmail::TYPE_REENGAGEMENT,
                'occurrence' => $occurrence,
                'local_date' => $localNow->toDateString(),
                'status' => LifecycleEmailLog::STATUS_SKIPPED,
                'error' => 'Skipped: a later step was due by the time this ran.',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $this->claim($user, $current, $occurrence, $localNow);
    }

    /**
     * When the user's current quiet spell started.
     */
    public static function inactivityAnchor(User $user): CarbonImmutable
    {
        return CarbonImmutable::parse($user->last_active_at ?? $user->last_login_at ?? $user->created_at ?? now());
    }

    /**
     * Identifies one inactivity spell: any new activity moves the anchor,
     * so the next spell gets a fresh set of steps.
     */
    public static function spellOccurrence(User $user): string
    {
        return 'spell:'.self::inactivityAnchor($user)->getTimestamp();
    }

    /**
     * Inserts the send-log row (the idempotency guard) and, only if this
     * call created it, queues the actual send.
     */
    public function claim(User $user, LifecycleEmail $email, string $occurrence, CarbonImmutable $localNow): bool
    {
        $inserted = LifecycleEmailLog::query()->insertOrIgnore([
            'user_id' => $user->id,
            'lifecycle_email_id' => $email->id,
            'type' => $email->type,
            'occurrence' => $occurrence,
            'local_date' => $localNow->toDateString(),
            'status' => LifecycleEmailLog::STATUS_QUEUED,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        $log = LifecycleEmailLog::where('user_id', $user->id)
            ->where('lifecycle_email_id', $email->id)
            ->where('occurrence', $occurrence)
            ->first();

        SendLifecycleEmail::dispatch($log->id);

        return true;
    }

    /**
     * Enabled festivals whose send date (for any country they could
     * apply to) falls on yesterday/today/tomorrow UTC — the only local
     * dates any user can be on right now.
     *
     * @return Collection<int, LifecycleEmail>
     */
    public function festivalsInWindow(CarbonImmutable $now): Collection
    {
        $window = [$now->subDay(), $now, $now->addDay()];

        return LifecycleEmail::ofType(LifecycleEmail::TYPE_FESTIVE)
            ->enabled()
            ->orderBy('sort_order')
            ->get()
            ->filter(function (LifecycleEmail $email) use ($window) {
                $countries = array_unique(array_merge(
                    [null],
                    $email->countryCodes(),
                    array_keys(($email->date_rule ?? [])['countries'] ?? []),
                ));

                foreach ($window as $date) {
                    foreach ($countries as $country) {
                        if (FestivalCalendar::occurrenceForSendDate($email, $date, $country) !== null) {
                            return true;
                        }
                    }
                }

                return false;
            })
            ->values();
    }

    /**
     * Everyone a lifecycle email may go to: not suspended, not deleted
     * (SoftDeletes' global scope), and opted in.
     */
    protected function recipients(): Builder
    {
        return User::query()
            ->where('is_suspended', false)
            ->where('marketing_emails_opt_in', true)
            ->whereNotNull('email');
    }

    /**
     * Re-engagement is for paying-customer accounts only: never team seats
     * (their owner is the customer), affiliate-only partner accounts, or
     * staff.
     */
    protected function reengagementCandidates(): Builder
    {
        return $this->recipients()
            ->whereNull('agency_owner_id')
            ->where(fn (Builder $q) => $q->where('is_affiliate_only', false)->orWhereNull('is_affiliate_only'))
            ->whereDoesntHave('roles', fn ($q) => $q->whereIn('name', self::STAFF_ROLES));
    }
}
