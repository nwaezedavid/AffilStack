<?php

namespace App\Support\Lifecycle;

use App\Models\LifecycleEmail;
use App\Models\User;
use App\Support\Countries;
use Carbon\CarbonImmutable;

/**
 * Who gets which lifecycle email, and when "today" and "9am" are for them.
 *
 * Festive targeting (the brief's rules):
 *   - religious festivals only reach users whose profile religion matches;
 *     users with no/undisclosed religion only get universal, cultural and
 *     national (their own country) greetings
 *   - any email with a country list only reaches users in those countries
 *     (national days, Thanksgiving, Lunar New Year, Mother's Day…)
 *   - at most one festive email per user per local day, highest priority
 *     first: religious > national > cultural > universal
 */
class LifecycleAudience
{
    /**
     * @var array<string, string>
     */
    public const RELIGIONS = [
        'christianity' => 'Christianity',
        'islam' => 'Islam',
        'hinduism' => 'Hinduism',
        'buddhism' => 'Buddhism',
        'judaism' => 'Judaism',
        'traditional' => 'Traditional / indigenous',
        'other' => 'Other',
        'none' => 'None',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    /**
     * @var array<string, string>
     */
    public const GENDERS = [
        'female' => 'Female',
        'male' => 'Male',
        'other' => 'Other',
        'prefer_not_to_say' => 'Prefer not to say',
    ];

    /**
     * Birth dates saved without a year are stored in this sentinel year
     * (a leap year, so 29 February survives) and the year is never shown
     * or used to work out an age.
     */
    public const NO_BIRTH_YEAR = 1904;

    public static function timezoneFor(User $user): string
    {
        if (Countries::isValidTimezone($user->timezone)) {
            return $user->timezone;
        }

        return Countries::mainTimezone(Countries::normalize($user->country)) ?? 'UTC';
    }

    public static function localNow(User $user, ?CarbonImmutable $now = null): CarbonImmutable
    {
        return ($now ?? CarbonImmutable::now())->setTimezone(self::timezoneFor($user));
    }

    /**
     * Sends go out from the send hour onwards on the local day — so a
     * missed cron run still catches up later that day, and the send log
     * stops it ever going twice.
     */
    public static function inSendWindow(CarbonImmutable $localNow): bool
    {
        return $localNow->hour >= LifecycleSettings::sendHour();
    }

    public static function isBirthday(User $user, CarbonImmutable $localDate): bool
    {
        if (! $user->birth_date) {
            return false;
        }

        $month = (int) $user->birth_date->format('n');
        $day = (int) $user->birth_date->format('j');

        if ($month === $localDate->month && $day === $localDate->day) {
            return true;
        }

        return $month === 2 && $day === 29
            && $localDate->month === 2 && $localDate->day === 28
            && ! $localDate->isLeapYear();
    }

    public static function hasBirthYear(User $user): bool
    {
        return $user->birth_date !== null && (int) $user->birth_date->format('Y') !== self::NO_BIRTH_YEAR;
    }

    public static function festiveEligible(LifecycleEmail $email, User $user): bool
    {
        $countries = $email->countryCodes();

        if ($countries !== [] && ! in_array(Countries::normalize($user->country), $countries, true)) {
            return false;
        }

        if ($email->category === LifecycleEmail::CATEGORY_RELIGIOUS) {
            return $email->religion !== null && $email->religion === $user->religion;
        }

        return true;
    }
}
