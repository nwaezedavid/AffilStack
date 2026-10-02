<?php

namespace App\Support\Lifecycle;

use App\Models\LifecycleEmail;

/**
 * The preset lifecycle emails AffilStack ships with — the birthday email,
 * every festive-season greeting, and the three-step re-engagement
 * sequence. install() only ever ADDS missing keys, so an admin's edits are
 * never overwritten (run by a migration, and by `lifecycle:sync-presets`).
 *
 * Lunar date tables (2026–2035) were cross-checked on 2026-10-01:
 *   - Ramadan / Eid al-Fitr / Eid al-Adha: the Umm al-Qura calendar (ICU
 *     islamic-umalqura) against calendar-yearly.com; where they differ by a
 *     day the Umm al-Qura date is used and the admin note says so. Actual
 *     dates depend on local moon sighting and can shift by a day per country.
 *   - Rosh Hashanah / Hanukkah: computed exactly from the Hebrew calendar
 *     (PHP jewishtojd); Hanukkah's first candle is lit the evening BEFORE
 *     the date listed (25 Kislev).
 *   - Diwali (Lakshmi Puja, India): diwali.info's 2026–2035 table.
 *   - Lunar New Year: travelchinaguide.com / chinahighlights.com.
 *   - Vesak: Singapore/Malaysia observance (15th day of the 4th Chinese
 *     lunar month) — 2026–2028 match the gazetted public holidays; later
 *     years are computed and should be confirmed when gazetted. Sri Lanka,
 *     India and Thailand often observe on a different full moon.
 */
class LifecycleEmailPresets
{
    public static function install(): int
    {
        $created = 0;

        foreach (self::all() as $index => $preset) {
            $email = LifecycleEmail::firstOrCreate(['key' => $preset['key']], $preset + ['sort_order' => $index]);

            if ($email->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function all(): array
    {
        return array_merge(
            [self::birthday()],
            self::festivals(),
            self::nationalDays(),
            self::reengagement(),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected static function birthday(): array
    {
        return [
            'key' => 'birthday',
            'type' => LifecycleEmail::TYPE_BIRTHDAY,
            'name' => 'Happy Birthday',
            'subject' => 'Happy birthday, {first_name}!',
            'preheader' => 'A little something from all of us at {site_name}.',
            'heading' => 'Happy birthday, {first_name}!',
            'body' => <<<'MD'
                Today is all about you, and everyone here at {site_name} wanted to say it properly: **happy birthday!**

                Thank you for building {business_name} with us. Every product you research, every post you publish and every lead you nurture is a step towards the business you're working for, and we're proud to be part of that journey.

                {if:bonus_credits}To celebrate, we've added **{bonus_credits} bonus credits** to your account. Spend them on anything you like: a fresh product research run, a week of content, or a new UGC video.{/if}

                Take a moment today to celebrate how far you've come. We'll be right here when you're ready for what's next.

                Warm wishes,
                The {site_name} team
                MD,
            'cta_label' => 'Open my dashboard',
            'cta_url' => '{dashboard_url}',
            'image_hint' => 'Optional. 1200×600 JPG or PNG: a warm celebration scene (confetti, balloons or a cake) in navy, blue and gold, with no words baked into the image.',
            'theme_color' => '#7C3AED',
            'enabled' => true,
            'bonus_credits' => 0,
            'admin_notes' => 'Sent at the send hour in the user\'s own timezone on their birthday (29 February birthdays get it on 28 February in other years). Only users who saved a birth day and month on their profile receive it.',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function festivals(): array
    {
        $lunarNote = 'Dates follow a lunar calendar and can move by a day depending on the local moon sighting. If your community celebrates on a different day, add a date override for that year.';

        return [
            self::festive('festive.new_year', "New Year's Day", LifecycleEmail::CATEGORY_UNIVERSAL, null, null,
                ['type' => 'fixed', 'month' => 1, 'day' => 1], '#0B1E3D',
                'Happy New Year, {first_name}!', 'New year, new wins. Here\'s to your best year yet.',
                'Happy New Year, {first_name}!',
                <<<'MD'
                    A brand-new year has arrived, and with it a clean slate for {business_name}.

                    Whatever last year looked like, this is your chance to set bigger goals, pick the products you believe in and show up for your audience consistently. Small, steady actions compound faster than you think.

                    Here's a simple way to start strong: research one new product this week, plan a month of content around it, and let your autopilot campaigns do the follow-up.

                    From all of us at {site_name}: may this be your most profitable year yet.
                    MD,
                'Plan my first win of the year', 'Optional. 1200×600: fireworks or a sunrise over a city skyline in navy and gold, no text.'),

            self::festive('festive.valentines', "Valentine's Day", LifecycleEmail::CATEGORY_UNIVERSAL, null, null,
                ['type' => 'fixed', 'month' => 2, 'day' => 14], '#E11D48',
                'A little love for {business_name}', 'Your audience will love this, {first_name}.',
                "Happy Valentine's Day, {first_name}!",
                <<<'MD'
                    Today is about the people we love, and that includes the audience that keeps your business going.

                    Show them some love back: share something genuinely useful, answer a question you keep getting, or recommend a product you'd happily buy yourself. Trust is the most valuable thing an affiliate can earn.

                    Wishing you a lovely day from all of us at {site_name}.
                    MD,
                'Create something they\'ll love', 'Optional. 1200×600: soft hearts or roses in rose and gold tones, no text.', false),

            self::festive('festive.good_friday', 'Good Friday', LifecycleEmail::CATEGORY_RELIGIOUS, 'christianity', null,
                ['type' => 'easter', 'offset' => -2], '#4C1D95',
                'Wishing you a peaceful Good Friday', 'A moment of reflection from all of us at {site_name}.',
                'A peaceful Good Friday to you, {first_name}',
                <<<'MD'
                    Today many Christians around the world pause to reflect on sacrifice, grace and hope.

                    We hope you find a quiet moment of peace today, with family, in worship, or simply away from the screen. Your business will still be here tomorrow.

                    With warm wishes from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a calm, reverent image (a cross at sunrise or soft light through a window), muted purple tones, no text.'),

            self::festive('festive.easter', 'Easter Sunday', LifecycleEmail::CATEGORY_RELIGIOUS, 'christianity', null,
                ['type' => 'easter', 'offset' => 0], '#059669',
                'Happy Easter, {first_name}!', 'Celebrating new beginnings with you.',
                'Happy Easter, {first_name}!',
                <<<'MD'
                    He is risen! Wishing you and your family a joyful Easter full of hope, renewal and celebration.

                    Easter is a season of new beginnings, a perfect time to breathe new life into {business_name}. When you're back at your desk, why not start something fresh: a new product, a new channel, a new idea you've been putting off?

                    Happy Easter from all of us at {site_name}.
                    MD,
                'Start something new', 'Optional. 1200×600: spring flowers, sunrise or pastel eggs in fresh greens and gold, no text.'),

            self::festive('festive.christmas', 'Christmas Day', LifecycleEmail::CATEGORY_RELIGIOUS, 'christianity', null,
                ['type' => 'fixed', 'month' => 12, 'day' => 25], '#B91C1C',
                'Merry Christmas, {first_name}!', 'Joy, peace and a well-earned rest.',
                'Merry Christmas, {first_name}!',
                <<<'MD'
                    Wishing you a very merry Christmas, filled with joy, peace and time with the people who matter most.

                    Thank you for building {business_name} with us this year. Every hour you put in adds up, so take today to rest, celebrate and recharge. You've earned it.

                    Merry Christmas and a prosperous new year from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a warm festive scene (lights, a tree or a nativity silhouette) in deep red, green and gold, no text.'),

            self::festive('festive.ramadan_start', 'Ramadan begins', LifecycleEmail::CATEGORY_RELIGIOUS, 'islam', null,
                ['type' => 'table', 'dates' => ['2026-02-18', '2027-02-08', '2028-01-28', '2029-01-16', '2030-01-05', '2030-12-26', '2031-12-16', '2032-12-04', '2033-11-23', '2034-11-12', '2035-11-01']], '#047857',
                'Ramadan Kareem, {first_name}', 'Wishing you a blessed and peaceful month.',
                'Ramadan Kareem, {first_name}',
                <<<'MD'
                    As the holy month of Ramadan begins, we wish you and your loved ones a blessed, peaceful and rewarding month.

                    May your fasts be accepted and your prayers answered. If your days look a little different this month, remember that {site_name} can keep working in the background: schedule your content and let your autopilot follow-ups run while you focus on what matters.

                    Ramadan Mubarak from all of us at {site_name}.
                    MD,
                'Schedule my content', 'Optional. 1200×600: a crescent moon and lanterns at dusk in deep green and gold, no text.', true, $lunarNote.' 2031: Umm al-Qura gives 16 Dec, some calendars 15 Dec.'),

            self::festive('festive.eid_al_fitr', 'Eid al-Fitr', LifecycleEmail::CATEGORY_RELIGIOUS, 'islam', null,
                ['type' => 'table', 'dates' => ['2026-03-20', '2027-03-09', '2028-02-26', '2029-02-14', '2030-02-04', '2031-01-24', '2032-01-14', '2033-01-03', '2033-12-23', '2034-12-12', '2035-12-01']], '#047857',
                'Eid Mubarak, {first_name}!', 'Celebrating the end of Ramadan with you.',
                'Eid Mubarak, {first_name}!',
                <<<'MD'
                    Eid Mubarak! As Ramadan comes to a close, we wish you and your family a joyful celebration full of blessings, good food and great company.

                    May the discipline and focus of the past month carry into everything you build next, {business_name} included.

                    Eid Mubarak from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a festive crescent, lanterns and warm lights in emerald and gold, no text.', true, $lunarNote.' 2027 and 2033: some calendars give 10 Mar 2027 / 2 Jan 2033.'),

            self::festive('festive.eid_al_adha', 'Eid al-Adha', LifecycleEmail::CATEGORY_RELIGIOUS, 'islam', null,
                ['type' => 'table', 'dates' => ['2026-05-27', '2027-05-16', '2028-05-05', '2029-04-24', '2030-04-13', '2031-04-02', '2032-03-22', '2033-03-12', '2034-03-01', '2035-02-19']], '#047857',
                'Eid al-Adha Mubarak, {first_name}', 'Wishing you a blessed Eid.',
                'Eid al-Adha Mubarak, {first_name}',
                <<<'MD'
                    Eid al-Adha Mubarak! We wish you and your loved ones a blessed celebration of faith, generosity and gratitude.

                    May your sacrifices be accepted and your efforts, in life and in business, be rewarded many times over.

                    With warm wishes from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a calm celebration scene (mosque silhouette, crescent, warm evening light) in emerald and gold, no text.', true, $lunarNote.' 2033 and 2035: some calendars give 11 Mar 2033 / 18 Feb 2035.'),

            self::festive('festive.diwali', 'Diwali', LifecycleEmail::CATEGORY_RELIGIOUS, 'hinduism', null,
                ['type' => 'table', 'dates' => ['2026-11-08', '2027-10-29', '2028-10-17', '2029-11-05', '2030-10-26', '2031-11-14', '2032-11-02', '2033-10-22', '2034-11-10', '2035-10-30']], '#D97706',
                'Happy Diwali, {first_name}!', 'May the festival of lights bring you prosperity.',
                'Happy Diwali, {first_name}!',
                <<<'MD'
                    Happy Diwali! May the festival of lights fill your home with joy, your heart with peace and your year with prosperity.

                    As you welcome Lakshmi into your home, here's to abundance for {business_name} too: new customers, new commissions and new opportunities.

                    Shubh Deepavali from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: glowing diyas and rangoli in warm amber, saffron and gold, no text.', true, 'Dates are the main Lakshmi Puja day in India; some regions (e.g. South India) celebrate a day earlier.'),

            self::festive('festive.rosh_hashanah', 'Rosh Hashanah', LifecycleEmail::CATEGORY_RELIGIOUS, 'judaism', null,
                ['type' => 'table', 'dates' => ['2026-09-12', '2027-10-02', '2028-09-21', '2029-09-10', '2030-09-28', '2031-09-18', '2032-09-06', '2033-09-24', '2034-09-14', '2035-10-04']], '#1D4ED8',
                'Shana Tova, {first_name}!', 'Wishing you a sweet new year.',
                'Shana Tova, {first_name}!',
                <<<'MD'
                    Shana Tova! As the new year begins, we wish you and your family a sweet, healthy and fulfilling year ahead.

                    May the year bring growth, good health and plenty of sweetness, for you and for {business_name}.

                    With warm wishes from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: apples and honey or a shofar in warm blue and gold tones, no text.', true, 'Dates are the first day (1 Tishrei), computed from the Hebrew calendar; the holiday begins at sundown the evening before.'),

            self::festive('festive.hanukkah', 'Hanukkah', LifecycleEmail::CATEGORY_RELIGIOUS, 'judaism', null,
                ['type' => 'table', 'dates' => ['2026-12-05', '2027-12-25', '2028-12-13', '2029-12-02', '2030-12-21', '2031-12-10', '2032-11-28', '2033-12-17', '2034-12-07', '2035-12-26']], '#1D4ED8',
                'Happy Hanukkah, {first_name}!', 'Eight nights of light, from all of us.',
                'Happy Hanukkah, {first_name}!',
                <<<'MD'
                    Chag Sameach! Wishing you and your loved ones a bright and joyful Hanukkah.

                    May each candle you light bring warmth, light and a little extra miracle into the days ahead.

                    Happy Hanukkah from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a glowing menorah in deep blue, silver and gold, no text.', true, 'Dates are the first full day (25 Kislev), computed from the Hebrew calendar; the first candle is lit the evening before.'),

            self::festive('festive.vesak', 'Vesak', LifecycleEmail::CATEGORY_RELIGIOUS, 'buddhism', null,
                ['type' => 'table', 'dates' => ['2026-05-31', '2027-05-20', '2028-05-09', '2029-05-27', '2030-05-16', '2031-06-04', '2032-05-23', '2033-05-13', '2034-06-01', '2035-05-22']], '#CA8A04',
                'Happy Vesak, {first_name}', 'Wishing you peace, wisdom and compassion.',
                'Happy Vesak, {first_name}',
                <<<'MD'
                    Happy Vesak! On this day of reflection, we wish you peace, wisdom and compassion, for you and everyone you love.

                    May the year ahead bring clarity to your path and kindness to everything you build.

                    With warm wishes from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: lotus flowers or lanterns in soft gold and saffron, no text.', true, 'Dates follow the Singapore/Malaysia observance (15th day of the 4th lunar month; 2026–2028 match the gazetted holidays, later years should be confirmed). Sri Lanka, India and Thailand sometimes observe on a different full moon (e.g. 1 May 2026), so add an override if most of your Buddhist users are there.'),

            self::festive('festive.lunar_new_year', 'Lunar New Year', LifecycleEmail::CATEGORY_CULTURAL, null, ['CN', 'HK', 'MO', 'TW', 'SG', 'MY', 'KR', 'VN'],
                ['type' => 'table', 'dates' => ['2026-02-17', '2027-02-06', '2028-01-26', '2029-02-13', '2030-02-03', '2031-01-23', '2032-02-11', '2033-01-31', '2034-02-19', '2035-02-08']], '#DC2626',
                'Happy Lunar New Year, {first_name}!', 'Wishing you luck and prosperity in the year ahead.',
                'Happy Lunar New Year, {first_name}!',
                <<<'MD'
                    Wishing you and your family a happy, healthy and prosperous Lunar New Year!

                    May the new year bring good fortune to your home and plenty of growth to {business_name}.

                    With warm wishes from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: red lanterns, plum blossom or fireworks in red and gold, no text.', true, 'Sent only to users in the listed countries, whatever their religion.'),

            self::festive('festive.thanksgiving_us', 'Thanksgiving (US)', LifecycleEmail::CATEGORY_NATIONAL, null, ['US'],
                ['type' => 'nth_weekday', 'month' => 11, 'weekday' => 4, 'n' => 4], '#C2410C',
                'Happy Thanksgiving, {first_name}!', 'We\'re thankful for you.',
                'Happy Thanksgiving, {first_name}!',
                <<<'MD'
                    Happy Thanksgiving! Today we're especially grateful for you, and for the chance to help you build {business_name}.

                    We hope your day is full of good food, good company and a real break from the hustle.

                    With gratitude from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: an autumn harvest table or falling leaves in warm orange and gold, no text.', true, 'Fourth Thursday of November, US users only.'),

            self::festive('festive.thanksgiving_ca', 'Thanksgiving (Canada)', LifecycleEmail::CATEGORY_NATIONAL, null, ['CA'],
                ['type' => 'nth_weekday', 'month' => 10, 'weekday' => 1, 'n' => 2], '#C2410C',
                'Happy Thanksgiving, {first_name}!', 'We\'re thankful for you.',
                'Happy Thanksgiving, {first_name}!',
                <<<'MD'
                    Happy Thanksgiving! Today we're especially grateful for you, and for the chance to help you build {business_name}.

                    We hope your long weekend is full of good food, good company and a well-earned rest.

                    With gratitude from all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: an autumn harvest table or maple leaves in warm orange and gold, no text.', true, 'Second Monday of October, Canadian users only.'),

            self::festive('festive.mothers_day', "Mother's Day", LifecycleEmail::CATEGORY_CULTURAL, null, ['US', 'CA', 'GB', 'IE', 'NG', 'GH', 'KE', 'ZA', 'IN', 'AU'],
                [
                    'type' => 'by_country',
                    'default' => ['type' => 'nth_weekday', 'month' => 5, 'weekday' => 7, 'n' => 2],
                    'countries' => [
                        'GB' => ['type' => 'easter', 'offset' => -21],
                        'IE' => ['type' => 'easter', 'offset' => -21],
                        'NG' => ['type' => 'easter', 'offset' => -21],
                    ],
                ], '#DB2777',
                "Happy Mother's Day", 'Celebrating the mothers in our lives.',
                "Happy Mother's Day, {first_name}",
                <<<'MD'
                    Today we're celebrating mothers and mother figures everywhere: the people who believe in us first and support us the longest.

                    Whether you're celebrating your mum, being celebrated yourself, or remembering someone special, we're sending you warm wishes today.

                    From all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: soft flowers in pink and gold, no text.', false, 'Second Sunday of May in most listed countries; Mothering Sunday (three weeks before Easter) in the UK, Ireland and Nigeria. Off by default since not everyone welcomes this one.'),

            self::festive('festive.fathers_day', "Father's Day", LifecycleEmail::CATEGORY_CULTURAL, null, ['US', 'CA', 'GB', 'IE', 'NG', 'GH', 'KE', 'ZA', 'IN', 'AU'],
                [
                    'type' => 'by_country',
                    'default' => ['type' => 'nth_weekday', 'month' => 6, 'weekday' => 7, 'n' => 3],
                    'countries' => [
                        'AU' => ['type' => 'nth_weekday', 'month' => 9, 'weekday' => 7, 'n' => 1],
                    ],
                ], '#1E40AF',
                "Happy Father's Day", 'Celebrating the fathers in our lives.',
                "Happy Father's Day, {first_name}",
                <<<'MD'
                    Today we're celebrating fathers and father figures everywhere: the people who taught us to work hard, keep going and look after the people around us.

                    Whether you're celebrating your dad, being celebrated yourself, or remembering someone special, we're sending you warm wishes today.

                    From all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a warm, simple image in navy and gold, no text.', false, 'Third Sunday of June in most listed countries; first Sunday of September in Australia. Off by default.'),

            self::festive('festive.workers_day', "International Workers' Day", LifecycleEmail::CATEGORY_UNIVERSAL, null, null,
                ['type' => 'fixed', 'month' => 5, 'day' => 1], '#0F766E',
                "Happy Workers' Day, {first_name}", 'Celebrating the work you put in.',
                "Happy Workers' Day, {first_name}!",
                <<<'MD'
                    Happy Workers' Day! Building {business_name} takes real work, and today we're celebrating every hour you've put in.

                    If you're enjoying a day off, make it a proper one. And when you're back, let {site_name} take a little more of the busywork off your plate.

                    From all of us at {site_name}.
                    MD,
                null, 'Optional. 1200×600: a bright, optimistic workspace or team illustration in teal and gold, no text.', false),
        ];
    }

    /**
     * Independence/national days for the countries most of AffilStack's
     * audience lives in (the UK has no national day, so it isn't listed).
     *
     * @return array<int, array<string, mixed>>
     */
    protected static function nationalDays(): array
    {
        $days = [
            ['NG', 'Nigeria Independence Day', "Nigeria's Independence Day", 10, 1, '#008751', 'Nigeria'],
            ['GH', 'Ghana Independence Day', "Ghana's Independence Day", 3, 6, '#006B3F', 'Ghana'],
            ['KE', 'Kenya Jamhuri Day', 'Jamhuri Day', 12, 12, '#006600', 'Kenya'],
            ['ZA', 'South Africa Freedom Day', 'Freedom Day', 4, 27, '#007A4D', 'South Africa'],
            ['US', 'US Independence Day', 'Independence Day', 7, 4, '#3C3B6E', 'America'],
            ['CA', 'Canada Day', 'Canada Day', 7, 1, '#D52B1E', 'Canada'],
            ['IN', 'India Independence Day', "India's Independence Day", 8, 15, '#FF9933', 'India'],
            ['UG', 'Uganda Independence Day', "Uganda's Independence Day", 10, 9, '#D21034', 'Uganda'],
            ['TZ', 'Tanzania Independence Day', "Tanzania's Independence Day", 12, 9, '#1EB53A', 'Tanzania'],
            ['ZM', 'Zambia Independence Day', "Zambia's Independence Day", 10, 24, '#198A00', 'Zambia'],
            ['ZW', 'Zimbabwe Independence Day', "Zimbabwe's Independence Day", 4, 18, '#006400', 'Zimbabwe'],
            ['CM', 'Cameroon National Day', "Cameroon's National Day", 5, 20, '#007A5E', 'Cameroon'],
            ['SL', 'Sierra Leone Independence Day', "Sierra Leone's Independence Day", 4, 27, '#1EB53A', 'Sierra Leone'],
            ['LR', 'Liberia Independence Day', "Liberia's Independence Day", 7, 26, '#002868', 'Liberia'],
            ['RW', 'Rwanda Independence Day', "Rwanda's Independence Day", 7, 1, '#00A1DE', 'Rwanda'],
            ['JM', 'Jamaica Independence Day', "Jamaica's Independence Day", 8, 6, '#009B3A', 'Jamaica'],
            ['PH', 'Philippines Independence Day', 'Philippine Independence Day', 6, 12, '#0038A8', 'the Philippines'],
            ['PK', 'Pakistan Independence Day', "Pakistan's Independence Day", 8, 14, '#01411C', 'Pakistan'],
            ['AE', 'UAE National Day', 'UAE National Day', 12, 2, '#00732F', 'the UAE'],
        ];

        return array_map(fn (array $day) => self::festive(
            'festive.national_'.strtolower($day[0]),
            $day[1],
            LifecycleEmail::CATEGORY_NATIONAL,
            null,
            [$day[0]],
            ['type' => 'fixed', 'month' => $day[3], 'day' => $day[4]],
            $day[5],
            "Happy {$day[2]}, {first_name}!",
            'Celebrating '.$day[6].' with you today.',
            "Happy {$day[2]}!",
            <<<MD
                Happy {$day[2]}! Today we're celebrating {$day[6]}: its people, its spirit and the entrepreneurs like you who are building its future.

                Every business you grow creates opportunity for the people around you. Thank you for building {business_name} with us.

                Enjoy the celebrations, from all of us at {site_name}.
                MD,
            null,
            'Optional. 1200×600: a celebratory image in the colours of the national flag (bunting, fireworks or a city skyline), no text and no altered flag.',
            true,
            "Sent only to users whose profile country is {$day[6]}.",
        ), $days);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected static function reengagement(): array
    {
        return [
            [
                'key' => 'reengagement.step_1',
                'type' => LifecycleEmail::TYPE_REENGAGEMENT,
                'name' => 'Re-engagement 1: pick up where you left off',
                'inactive_days' => 7,
                'subject' => '{first_name}, your next affiliate win is waiting',
                'preheader' => 'Everything is saved right where you left it.',
                'heading' => 'Pick up where you left off',
                'body' => <<<'MD'
                    Hi {first_name},

                    It's been {days_inactive} days since you last dropped into {site_name}, so here's a quick nudge.

                    {if:product_name}Last time you were working on **{product_name}**. Your research, angles and drafts are all saved, so you can carry on in one click.{/if}{unless:product_name}Your next winning product is one research run away. Tell us what you'd like to promote and we'll map out the audience, the angle and the content for you.{/unless}

                    You still have **{credits} credits** ready to use.

                    See you inside,
                    The {site_name} team
                    MD,
                'cta_label' => 'Continue where I left off',
                'cta_url' => '{dashboard_url}',
                'image_hint' => 'Optional. 1200×600: a friendly dashboard or laptop illustration in brand blue and gold, no text.',
                'theme_color' => '#2452D9',
                'enabled' => true,
                'admin_notes' => 'Sent once per inactivity spell. If a user comes back, the sequence stops immediately and starts fresh the next time they go quiet.',
            ],
            [
                'key' => 'reengagement.step_2',
                'type' => LifecycleEmail::TYPE_REENGAGEMENT,
                'name' => 'Re-engagement 2: three 10-minute wins',
                'inactive_days' => 14,
                'subject' => '3 quick wins you can get in 10 minutes, {first_name}',
                'preheader' => 'Small steps, real commissions.',
                'heading' => 'Three quick wins for this week',
                'body' => <<<'MD'
                    Hi {first_name},

                    Been busy? Totally understandable. Growing {business_name} doesn't need hours, though. Here are three things you can do in about ten minutes each:

                    1. **Research one product.** Paste a product link and get the ideal customer, the best channel and a ready-made angle.
                    2. **Generate a week of content.** Turn that research into posts, a blog article or a short video script.
                    3. **Switch on a follow-up.** Let an autopilot email sequence nurture your leads while you get on with your day.

                    {if:product_name}Your work on **{product_name}** is still saved, so that's a great place to start.{/if}

                    You have **{credits} credits** waiting.

                    Cheering you on,
                    The {site_name} team
                    MD,
                'cta_label' => 'Get my first quick win',
                'cta_url' => '{dashboard_url}',
                'image_hint' => 'Optional. 1200×600: a simple checklist or stopwatch illustration in brand blue and gold, no text.',
                'theme_color' => '#0D9488',
                'enabled' => true,
                'admin_notes' => 'Sent once per inactivity spell, only after step 1.',
            ],
            [
                'key' => 'reengagement.step_3',
                'type' => LifecycleEmail::TYPE_REENGAGEMENT,
                'name' => 'Re-engagement 3: we miss you',
                'inactive_days' => 30,
                'subject' => 'We miss you, {first_name}',
                'preheader' => 'Can we help you get back on track?',
                'heading' => 'We miss you, {first_name}',
                'body' => <<<'MD'
                    Hi {first_name},

                    It's been a while since we last saw you, and we wanted to check in personally.

                    If something got in the way, whether it was time, a feature that didn't click or a question you didn't get answered, we'd genuinely love to help. Open the support chat in your dashboard and a real person will get back to you.

                    {if:product_name}Your work on **{product_name}** is still there whenever you're ready.{/if} Your account and your **{credits} credits** are waiting for you too.

                    This is the last nudge we'll send for now. We hope to see you back soon.

                    The {site_name} team
                    MD,
                'cta_label' => 'Come back to my dashboard',
                'cta_url' => '{dashboard_url}',
                'image_hint' => 'Optional. 1200×600: a warm "welcome back" illustration (an open door or a friendly wave) in brand blue and gold, no text.',
                'theme_color' => '#0B1E3D',
                'enabled' => true,
                'admin_notes' => 'The final step. After this, nothing more is sent until the user comes back and later goes quiet again.',
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $countries
     * @param  array<string, mixed>  $rule
     * @return array<string, mixed>
     */
    protected static function festive(
        string $key,
        string $name,
        string $category,
        ?string $religion,
        ?array $countries,
        array $rule,
        string $color,
        string $subject,
        string $preheader,
        string $heading,
        string $body,
        ?string $ctaLabel,
        string $imageHint,
        bool $enabled = true,
        ?string $notes = null,
    ): array {
        return [
            'key' => $key,
            'type' => LifecycleEmail::TYPE_FESTIVE,
            'name' => $name,
            'category' => $category,
            'religion' => $religion,
            'countries' => $countries,
            'date_rule' => $rule,
            'theme_color' => $color,
            'subject' => $subject,
            'preheader' => $preheader,
            'heading' => $heading,
            'body' => $body,
            'cta_label' => $ctaLabel,
            'cta_url' => $ctaLabel ? '{dashboard_url}' : null,
            'image_hint' => $imageHint,
            'enabled' => $enabled,
            'days_before' => 0,
            'admin_notes' => $notes,
        ];
    }
}
