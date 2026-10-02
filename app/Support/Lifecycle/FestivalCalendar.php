<?php

namespace App\Support\Lifecycle;

use App\Models\LifecycleEmail;
use Carbon\CarbonImmutable;

/**
 * Works out when each festive-season email's festival falls, from its
 * date_rule (see LifecycleEmail for the shapes) and any admin per-year
 * overrides. Pure date arithmetic — no network, no intl/calendar
 * extension: Easter uses the Anonymous Gregorian (Meeus/Jones/Butcher)
 * algorithm, Thanksgiving/Mother's Day etc. an nth-weekday rule, and
 * lunar festivals ship a verified date table (2026–2035) that the admin
 * can correct per year when a moon sighting moves a date.
 */
class FestivalCalendar
{
    /**
     * Easter Sunday in the Gregorian calendar (Western churches).
     */
    public static function easterSunday(int $year): CarbonImmutable
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return CarbonImmutable::create($year, $month, $day)->startOfDay();
    }

    /**
     * The nth given ISO weekday (1 = Monday … 7 = Sunday) of a month;
     * $n = -1 means the last one.
     */
    public static function nthWeekday(int $year, int $month, int $isoWeekday, int $n): CarbonImmutable
    {
        if ($n < 0) {
            $date = CarbonImmutable::create($year, $month, 1)->endOfMonth()->startOfDay();

            while ($date->dayOfWeekIso !== $isoWeekday) {
                $date = $date->subDay();
            }

            return $date;
        }

        $date = CarbonImmutable::create($year, $month, 1)->startOfDay();

        while ($date->dayOfWeekIso !== $isoWeekday) {
            $date = $date->addDay();
        }

        return $date->addWeeks(max(1, $n) - 1);
    }

    /**
     * Every date the festival falls on in a Gregorian year, for a user in
     * $country (only matters for "by_country" rules, e.g. Mothering
     * Sunday in the UK vs Mother's Day in the US).
     *
     * @return array<int, CarbonImmutable>
     */
    public static function datesInYear(LifecycleEmail $email, int $year, ?string $country = null): array
    {
        $overrides = collect($email->date_overrides ?? [])
            ->filter(fn ($row) => (int) ($row['year'] ?? 0) === $year && filled($row['date'] ?? null))
            ->map(fn ($row) => CarbonImmutable::parse($row['date'])->startOfDay())
            ->filter(fn (CarbonImmutable $date) => $date->year === $year)
            ->values()
            ->all();

        if ($overrides !== []) {
            return $overrides;
        }

        return self::datesFromRule($email->date_rule ?? [], $year, $country);
    }

    /**
     * @param  array<string, mixed>  $rule
     * @return array<int, CarbonImmutable>
     */
    public static function datesFromRule(array $rule, int $year, ?string $country = null): array
    {
        return match ($rule['type'] ?? null) {
            'fixed' => self::validDate($year, (int) ($rule['month'] ?? 0), (int) ($rule['day'] ?? 0)),
            'easter' => [self::easterSunday($year)->addDays((int) ($rule['offset'] ?? 0))],
            'nth_weekday' => [self::nthWeekday($year, (int) $rule['month'], (int) $rule['weekday'], (int) ($rule['n'] ?? 1))],
            'table' => collect($rule['dates'] ?? [])
                ->filter(fn ($date) => is_string($date) && str_starts_with($date, $year.'-'))
                ->map(fn (string $date) => CarbonImmutable::parse($date)->startOfDay())
                ->values()
                ->all(),
            'by_country' => self::datesFromRule(
                ($rule['countries'] ?? [])[strtoupper((string) $country)] ?? ($rule['default'] ?? []),
                $year,
                $country,
            ),
            default => [],
        };
    }

    /**
     * The festival occurrence this email should be SENT for on a user's
     * local date — the festival date minus the email's days_before — or
     * null when there's nothing to send that day. Checks the next year
     * too, so "1 day before New Year's Day" lands on 31 December.
     */
    public static function occurrenceForSendDate(LifecycleEmail $email, CarbonImmutable $localDate, ?string $country = null): ?CarbonImmutable
    {
        $localDate = $localDate->startOfDay();
        $daysBefore = max(0, (int) $email->days_before);

        foreach ([$localDate->year, $localDate->year + 1] as $year) {
            foreach (self::datesInYear($email, $year, $country) as $festival) {
                if ($festival->subDays($daysBefore)->equalTo($localDate)) {
                    return $festival;
                }
            }
        }

        return null;
    }

    /**
     * The next date (on or after $from) this festival falls, for the
     * admin table's "Next date" column.
     */
    public static function nextOccurrence(LifecycleEmail $email, CarbonImmutable $from, ?string $country = null): ?CarbonImmutable
    {
        $from = $from->startOfDay();

        foreach ([$from->year, $from->year + 1] as $year) {
            $dates = collect(self::datesInYear($email, $year, $country))
                ->filter(fn (CarbonImmutable $date) => $date->greaterThanOrEqualTo($from))
                ->sort()
                ->values();

            if ($dates->isNotEmpty()) {
                return $dates->first();
            }
        }

        return null;
    }

    /**
     * Whether a date-table rule has any entry for $year — the admin form
     * warns when a lunar table runs out so someone extends it in time.
     */
    public static function hasTableEntryFor(LifecycleEmail $email, int $year): bool
    {
        $rule = $email->date_rule ?? [];

        if (($rule['type'] ?? null) !== 'table') {
            return true;
        }

        return self::datesInYear($email, $year) !== [];
    }

    /**
     * @return array<int, CarbonImmutable>
     */
    protected static function validDate(int $year, int $month, int $day): array
    {
        if (! checkdate($month, $day, $year)) {
            return [];
        }

        return [CarbonImmutable::create($year, $month, $day)->startOfDay()];
    }
}
