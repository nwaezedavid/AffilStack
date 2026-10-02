<?php

namespace Tests\Feature;

use App\Models\LifecycleEmail;
use App\Support\Lifecycle\FestivalCalendar;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FestivalCalendarTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{int, string}>
     */
    public static function easterDates(): array
    {
        return [
            '2024' => [2024, '2024-03-31'],
            '2025' => [2025, '2025-04-20'],
            '2026' => [2026, '2026-04-05'],
            '2027' => [2027, '2027-03-28'],
            '2028' => [2028, '2028-04-16'],
            '2030' => [2030, '2030-04-21'],
            '2035' => [2035, '2035-03-25'],
        ];
    }

    #[DataProvider('easterDates')]
    public function test_easter_sunday_is_computed_correctly(int $year, string $expected): void
    {
        $this->assertSame($expected, FestivalCalendar::easterSunday($year)->toDateString());
    }

    public function test_presets_resolve_to_the_right_dates(): void
    {
        $expectations = [
            'festive.good_friday' => [2026, null, '2026-04-03'],
            'festive.christmas' => [2026, null, '2026-12-25'],
            'festive.thanksgiving_us' => [2026, null, '2026-11-26'],
            'festive.thanksgiving_ca' => [2026, null, '2026-10-12'],
            'festive.mothers_day' => [2026, 'US', '2026-05-10'],
            'festive.fathers_day' => [2026, 'NG', '2026-06-21'],
            'festive.national_ng' => [2026, null, '2026-10-01'],
            'festive.eid_al_fitr' => [2027, null, '2027-03-09'],
            'festive.lunar_new_year' => [2027, null, '2027-02-06'],
            'festive.diwali' => [2026, null, '2026-11-08'],
            'festive.rosh_hashanah' => [2026, null, '2026-09-12'],
        ];

        foreach ($expectations as $key => [$year, $country, $date]) {
            $email = LifecycleEmail::where('key', $key)->firstOrFail();
            $this->assertSame([$date], array_map(fn ($d) => $d->toDateString(), FestivalCalendar::datesInYear($email, $year, $country)), $key);
        }
    }

    public function test_mothers_day_uses_mothering_sunday_in_the_uk_and_nigeria(): void
    {
        $email = LifecycleEmail::where('key', 'festive.mothers_day')->firstOrFail();

        $this->assertSame('2026-03-15', FestivalCalendar::datesInYear($email, 2026, 'GB')[0]->toDateString());
        $this->assertSame('2026-03-15', FestivalCalendar::datesInYear($email, 2026, 'NG')[0]->toDateString());
        $this->assertSame('2026-05-10', FestivalCalendar::datesInYear($email, 2026, 'GH')[0]->toDateString());
    }

    public function test_fathers_day_is_in_september_in_australia(): void
    {
        $email = LifecycleEmail::where('key', 'festive.fathers_day')->firstOrFail();

        $this->assertSame('2026-09-06', FestivalCalendar::datesInYear($email, 2026, 'AU')[0]->toDateString());
    }

    public function test_a_lunar_festival_can_fall_twice_in_one_year(): void
    {
        $email = LifecycleEmail::where('key', 'festive.ramadan_start')->firstOrFail();

        $this->assertSame(['2030-01-05', '2030-12-26'], array_map(fn ($d) => $d->toDateString(), FestivalCalendar::datesInYear($email, 2030)));
    }

    public function test_an_admin_override_replaces_the_calculated_date_for_that_year_only(): void
    {
        $email = LifecycleEmail::where('key', 'festive.eid_al_fitr')->firstOrFail();
        $email->update(['date_overrides' => [['year' => 2027, 'date' => '2027-03-10']]]);

        $this->assertSame(['2027-03-10'], array_map(fn ($d) => $d->toDateString(), FestivalCalendar::datesInYear($email, 2027)));
        $this->assertSame(['2028-02-26'], array_map(fn ($d) => $d->toDateString(), FestivalCalendar::datesInYear($email, 2028)));
    }

    public function test_days_before_can_cross_into_the_previous_year(): void
    {
        $email = LifecycleEmail::where('key', 'festive.new_year')->firstOrFail();
        $email->days_before = 1;

        $occurrence = FestivalCalendar::occurrenceForSendDate($email, CarbonImmutable::parse('2026-12-31'));

        $this->assertSame('2027-01-01', $occurrence?->toDateString());
        $this->assertNull(FestivalCalendar::occurrenceForSendDate($email, CarbonImmutable::parse('2027-01-01')));
    }

    public function test_nth_weekday_supports_the_last_weekday_of_a_month(): void
    {
        $this->assertSame('2026-05-25', FestivalCalendar::nthWeekday(2026, 5, 1, -1)->toDateString());
    }

    public function test_every_preset_with_a_date_table_covers_2026_to_2035(): void
    {
        LifecycleEmail::where('type', LifecycleEmail::TYPE_FESTIVE)->get()
            ->filter(fn (LifecycleEmail $email) => ($email->date_rule['type'] ?? null) === 'table')
            ->each(function (LifecycleEmail $email) {
                foreach (range(2026, 2035) as $year) {
                    $this->assertNotEmpty(FestivalCalendar::datesInYear($email, $year), "{$email->key} has no date in {$year}");
                }
            });
    }
}
