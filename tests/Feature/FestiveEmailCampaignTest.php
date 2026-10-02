<?php

namespace Tests\Feature;

use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Lifecycle\LifecycleEmailDispatcher;
use App\Support\Lifecycle\LifecycleSettings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FestiveEmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function user(array $attributes = []): User
    {
        $user = User::factory()->create();
        $user->forceFill($attributes + ['country' => 'NG', 'timezone' => 'Africa/Lagos'])->save();

        return $user->fresh();
    }

    protected function runAt(string $utc): int
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

        return app(LifecycleEmailDispatcher::class)->dispatchFestive();
    }

    protected function assertGot(User $user, string $key): void
    {
        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($user->email) && $mail->email->key === $key);
    }

    protected function assertGotNothing(User $user): void
    {
        Mail::assertNotSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($user->email));
    }

    public function test_religious_festivals_only_reach_users_of_that_religion(): void
    {
        $christian = $this->user(['religion' => 'christianity']);
        $muslim = $this->user(['religion' => 'islam']);
        $undisclosed = $this->user(['religion' => 'prefer_not_to_say']);
        $blank = $this->user(['religion' => null]);

        $this->runAt('2026-12-25 09:00');

        $this->assertGot($christian, 'festive.christmas');
        $this->assertGotNothing($muslim);
        $this->assertGotNothing($undisclosed);
        $this->assertGotNothing($blank);
    }

    public function test_eid_goes_to_muslim_users_only(): void
    {
        $muslim = $this->user(['religion' => 'islam']);
        $christian = $this->user(['religion' => 'christianity']);

        $this->runAt('2027-03-09 10:00');

        $this->assertGot($muslim, 'festive.eid_al_fitr');
        $this->assertGotNothing($christian);
    }

    public function test_national_days_only_reach_users_in_that_country_whatever_their_religion(): void
    {
        $nigerian = $this->user(['country' => 'NG', 'religion' => 'islam']);
        $nigerianByName = $this->user(['country' => 'Nigeria', 'timezone' => null]);
        $ghanaian = $this->user(['country' => 'GH', 'timezone' => 'Africa/Accra']);

        $this->runAt('2026-10-01 09:00');

        $this->assertGot($nigerian, 'festive.national_ng');
        $this->assertGot($nigerianByName, 'festive.national_ng');
        $this->assertGotNothing($ghanaian);
    }

    public function test_users_without_a_religion_still_get_universal_greetings(): void
    {
        $blank = $this->user(['religion' => null]);
        $hindu = $this->user(['religion' => 'hinduism']);

        $this->runAt('2027-01-01 09:00');

        $this->assertGot($blank, 'festive.new_year');
        $this->assertGot($hindu, 'festive.new_year');
    }

    public function test_cultural_festivals_are_country_gated(): void
    {
        $singapore = $this->user(['country' => 'SG', 'timezone' => 'Asia/Singapore']);
        $nigeria = $this->user(['country' => 'NG']);

        $this->runAt('2027-02-06 02:00');

        $this->assertGot($singapore, 'festive.lunar_new_year');
        $this->assertGotNothing($nigeria);
    }

    public function test_only_one_festive_email_per_day_with_religious_beating_universal(): void
    {
        LifecycleEmail::create([
            'key' => 'custom.boxing_test', 'type' => LifecycleEmail::TYPE_FESTIVE, 'name' => 'Holiday test',
            'subject' => 'Season greetings', 'heading' => 'Season greetings', 'body' => 'Hello',
            'category' => LifecycleEmail::CATEGORY_UNIVERSAL, 'date_rule' => ['type' => 'fixed', 'month' => 12, 'day' => 25],
        ]);

        $christian = $this->user(['religion' => 'christianity']);
        $blank = $this->user(['religion' => null]);

        $this->runAt('2026-12-25 09:00');
        $this->runAt('2026-12-25 12:00');

        $this->assertGot($christian, 'festive.christmas');
        $this->assertGot($blank, 'custom.boxing_test');
        Mail::assertSent(LifecycleMail::class, 2);
        $this->assertSame(1, LifecycleEmailLog::where('user_id', $christian->id)->count());
    }

    public function test_it_waits_for_the_send_hour_in_each_users_timezone(): void
    {
        $lagos = $this->user(['religion' => 'christianity', 'timezone' => 'Africa/Lagos']);
        $nairobi = $this->user(['religion' => 'christianity', 'country' => 'KE', 'timezone' => null]);
        $losAngeles = $this->user(['religion' => 'christianity', 'country' => 'US', 'timezone' => 'America/Los_Angeles']);

        $this->runAt('2026-12-25 07:00');

        $this->assertGot($nairobi, 'festive.christmas');
        $this->assertGotNothing($lagos);
        $this->assertGotNothing($losAngeles);

        $this->runAt('2026-12-25 08:00');
        $this->assertGot($lagos, 'festive.christmas');
        $this->assertGotNothing($losAngeles);

        $this->runAt('2026-12-25 17:00');
        $this->assertGot($losAngeles, 'festive.christmas');
    }

    public function test_re_running_never_sends_twice(): void
    {
        $christian = $this->user(['religion' => 'christianity']);

        $this->assertSame(1, $this->runAt('2026-12-25 09:00'));
        $this->assertSame(0, $this->runAt('2026-12-25 10:00'));
        $this->assertSame(0, $this->runAt('2026-12-25 20:00'));

        Mail::assertSent(LifecycleMail::class, 1);
        $this->assertSame(LifecycleEmailLog::STATUS_SENT, LifecycleEmailLog::where('user_id', $christian->id)->value('status'));
    }

    public function test_opted_out_suspended_and_disabled_emails_are_respected(): void
    {
        $optedOut = $this->user(['religion' => 'christianity', 'marketing_emails_opt_in' => false]);
        $suspended = $this->user(['religion' => 'christianity', 'is_suspended' => true]);

        $this->runAt('2026-12-25 09:00');

        $this->assertGotNothing($optedOut);
        $this->assertGotNothing($suspended);

        LifecycleEmail::where('key', 'festive.national_ng')->update(['enabled' => false]);
        $nigerian = $this->user();
        $this->runAt('2026-10-01 09:00');
        $this->assertGotNothing($nigerian);
    }

    public function test_days_before_sends_early(): void
    {
        LifecycleEmail::where('key', 'festive.christmas')->update(['days_before' => 1]);
        $christian = $this->user(['religion' => 'christianity']);

        $this->runAt('2026-12-24 09:00');

        $this->assertGot($christian, 'festive.christmas');
        $this->assertSame('2026-12-25', LifecycleEmailLog::where('user_id', $christian->id)->value('occurrence'));
    }

    public function test_the_festive_master_switch_pauses_everything(): void
    {
        LifecycleSettings::save(['festive_enabled' => false]);
        $christian = $this->user(['religion' => 'christianity']);

        $this->assertSame(0, $this->runAt('2026-12-25 09:00'));
        $this->assertGotNothing($christian);
    }

    public function test_the_email_is_personalised_and_carries_one_click_unsubscribe_headers(): void
    {
        $christian = $this->user(['religion' => 'christianity', 'name' => 'Ada Obi', 'company_name' => 'Obi Media']);

        $this->runAt('2026-12-25 09:00');

        Mail::assertSent(LifecycleMail::class, function (LifecycleMail $mail) {
            $html = $mail->render();
            $headers = $mail->headers()->text;

            return $mail->envelope()->subject === 'Merry Christmas, Ada!'
                && str_contains($html, 'Obi Media')
                && str_contains($headers['List-Unsubscribe'], '/email/lifecycle/unsubscribe/')
                && $headers['List-Unsubscribe-Post'] === 'List-Unsubscribe=One-Click';
        });
    }
}
