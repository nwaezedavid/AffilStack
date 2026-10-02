<?php

namespace Tests\Feature;

use App\Jobs\SendLifecycleEmail;
use App\Mail\LifecycleMail;
use App\Models\CreditLedger;
use App\Models\LifecycleEmail;
use App\Models\LifecycleEmailLog;
use App\Models\User;
use App\Services\Agents\SamAgentService;
use App\Services\Lifecycle\LifecycleEmailDispatcher;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BirthdayEmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    protected function user(string $birthDate, array $attributes = []): User
    {
        $user = User::factory()->create(['credits_balance' => 100]);
        $user->forceFill($attributes + ['birth_date' => $birthDate, 'country' => 'NG', 'timezone' => 'Africa/Lagos'])->save();

        return $user->fresh();
    }

    protected function runAt(string $utc): int
    {
        $this->travelTo(CarbonImmutable::parse($utc, 'UTC'));

        return app(LifecycleEmailDispatcher::class)->dispatchBirthdays();
    }

    public function test_it_sends_on_the_users_birthday_at_the_send_hour_in_their_timezone(): void
    {
        $birthday = $this->user('1990-11-14');
        $notToday = $this->user('1990-11-15');

        $this->assertSame(0, $this->runAt('2026-11-14 07:30'));
        $this->assertSame(1, $this->runAt('2026-11-14 08:05'));

        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($birthday->email) && $mail->email->type === LifecycleEmail::TYPE_BIRTHDAY);
        Mail::assertNotSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($notToday->email));
    }

    public function test_a_birthday_without_a_year_still_works(): void
    {
        $user = $this->user('1904-11-14');

        $this->runAt('2026-11-14 09:00');

        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($user->email));
    }

    public function test_leap_day_birthdays_are_celebrated_on_28_february_in_other_years(): void
    {
        $leapling = $this->user('1992-02-29');

        $this->runAt('2027-02-28 09:00');
        Mail::assertSent(LifecycleMail::class, 1);

        $this->runAt('2028-02-28 09:00');
        Mail::assertSent(LifecycleMail::class, 1);

        $this->runAt('2028-02-29 09:00');
        Mail::assertSent(LifecycleMail::class, 2);
        $this->assertSame(['2027', '2028'], LifecycleEmailLog::where('user_id', $leapling->id)->orderBy('occurrence')->pluck('occurrence')->all());
    }

    public function test_it_goes_out_once_per_year(): void
    {
        $user = $this->user('1990-11-14');

        $this->runAt('2026-11-14 09:00');
        $this->runAt('2026-11-14 15:00');
        $this->runAt('2026-11-14 22:00');

        Mail::assertSent(LifecycleMail::class, 1);

        $this->runAt('2027-11-14 09:00');
        Mail::assertSent(LifecycleMail::class, 2);
        $this->assertSame(2, LifecycleEmailLog::where('user_id', $user->id)->count());
    }

    public function test_no_gift_by_default_and_the_gift_paragraph_is_left_out(): void
    {
        $user = $this->user('1990-11-14');

        $this->runAt('2026-11-14 09:00');

        $this->assertSame(100, $user->fresh()->credits_balance);
        $this->assertSame(0, CreditLedger::where('reason', 'birthday_gift')->count());
        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => ! str_contains($mail->render(), 'bonus credits'));
    }

    public function test_an_admin_configured_gift_is_credited_once_through_the_ledger(): void
    {
        LifecycleEmail::where('key', 'birthday')->update(['bonus_credits' => 50]);
        $user = $this->user('1990-11-14');

        $this->runAt('2026-11-14 09:00');
        $this->runAt('2026-11-14 10:00');

        $this->assertSame(150, $user->fresh()->credits_balance);
        $ledger = CreditLedger::where('user_id', $user->id)->where('reason', 'birthday_gift')->sole();
        $this->assertSame(50, $ledger->amount);
        $this->assertSame(50, LifecycleEmailLog::where('user_id', $user->id)->value('bonus_credits_granted'));
        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => str_contains($mail->render(), '50 bonus credits'));
    }

    public function test_team_seats_get_the_greeting_without_a_gift(): void
    {
        LifecycleEmail::where('key', 'birthday')->update(['bonus_credits' => 50]);
        $owner = User::factory()->create(['credits_balance' => 100]);
        $seat = $this->user('1990-11-14', ['agency_owner_id' => $owner->id]);

        $this->runAt('2026-11-14 09:00');

        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($seat->email) && ! str_contains($mail->render(), 'bonus credits'));
        $this->assertSame(100, $owner->fresh()->credits_balance);
    }

    public function test_opted_out_users_and_users_without_a_birthday_are_skipped(): void
    {
        $this->user('1990-11-14', ['marketing_emails_opt_in' => false]);
        User::factory()->create();

        $this->assertSame(0, $this->runAt('2026-11-14 09:00'));
        Mail::assertNothingSent();
    }

    public function test_a_user_who_opts_out_after_it_was_queued_is_skipped_at_send_time(): void
    {
        $user = $this->user('1990-11-14');
        $email = LifecycleEmail::where('key', 'birthday')->first();
        $log = LifecycleEmailLog::create(['user_id' => $user->id, 'lifecycle_email_id' => $email->id, 'type' => 'birthday', 'occurrence' => '2026']);
        $user->forceFill(['marketing_emails_opt_in' => false])->save();

        (new SendLifecycleEmail($log->id))->handle(app(SamAgentService::class));

        Mail::assertNothingSent();
        $this->assertSame(LifecycleEmailLog::STATUS_SKIPPED, $log->fresh()->status);
    }
}
