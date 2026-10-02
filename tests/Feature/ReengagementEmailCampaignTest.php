<?php

namespace Tests\Feature;

use App\Jobs\SendLifecycleEmail;
use App\Mail\LifecycleMail;
use App\Models\LifecycleEmail;
use App\Models\LifecycleEmailLog;
use App\Models\Offer;
use App\Models\User;
use App\Services\Agents\SamAgentService;
use App\Services\Lifecycle\LifecycleEmailDispatcher;
use App\Support\Lifecycle\LifecycleSettings;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ReengagementEmailCampaignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo(CarbonImmutable::parse('2026-11-20 09:30', 'UTC'));
    }

    protected function inactiveUser(int $days, array $attributes = []): User
    {
        $user = User::factory()->create(['credits_balance' => 320, 'created_at' => now()->subDays(200)]);
        $user->forceFill($attributes + [
            'last_active_at' => now()->subDays($days),
            'last_login_at' => now()->subDays($days),
            'timezone' => 'Africa/Lagos',
        ])->save();

        return $user->fresh();
    }

    protected function dispatchNow(): int
    {
        return app(LifecycleEmailDispatcher::class)->dispatchReengagement();
    }

    /**
     * @return array<int, string>
     */
    protected function stepsSentTo(User $user): array
    {
        return LifecycleEmailLog::where('user_id', $user->id)->where('status', LifecycleEmailLog::STATUS_SENT)
            ->with('lifecycleEmail')->get()->pluck('lifecycleEmail.key')->sort()->values()->all();
    }

    public function test_recently_active_users_get_nothing(): void
    {
        $this->inactiveUser(3);

        $this->assertSame(0, $this->dispatchNow());
        Mail::assertNothingSent();
    }

    public function test_step_one_after_seven_days_mentions_their_product_and_credits(): void
    {
        $user = $this->inactiveUser(8);
        Offer::create(['user_id' => $user->id, 'product_name' => 'Systeme.io', 'product_url' => 'https://systeme.io', 'affiliate_network' => 'Direct', 'status' => 'ready']);

        $this->assertSame(1, $this->dispatchNow());

        $this->assertSame(['reengagement.step_1'], $this->stepsSentTo($user));
        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => str_contains($mail->render(), 'Systeme.io') && str_contains($mail->render(), '320 credits'));
    }

    public function test_each_step_goes_once_per_spell_and_the_sequence_progresses(): void
    {
        $user = $this->inactiveUser(8);

        $this->dispatchNow();
        $this->dispatchNow();
        $this->assertSame(['reengagement.step_1'], $this->stepsSentTo($user));

        $this->travel(7)->days();
        $this->dispatchNow();
        $this->assertSame(['reengagement.step_1', 'reengagement.step_2'], $this->stepsSentTo($user));

        $this->travel(16)->days();
        $this->dispatchNow();
        $this->dispatchNow();
        $this->assertSame(['reengagement.step_1', 'reengagement.step_2', 'reengagement.step_3'], $this->stepsSentTo($user));

        $this->travel(60)->days();
        $this->assertSame(0, $this->dispatchNow());
        Mail::assertSent(LifecycleMail::class, 3);
    }

    public function test_a_long_dormant_user_gets_only_the_latest_step(): void
    {
        $user = $this->inactiveUser(45);

        $this->dispatchNow();

        $this->assertSame(['reengagement.step_3'], $this->stepsSentTo($user));
        $this->assertSame(2, LifecycleEmailLog::where('user_id', $user->id)->where('status', LifecycleEmailLog::STATUS_SKIPPED)->count());
        Mail::assertSent(LifecycleMail::class, 1);
    }

    public function test_it_stops_the_moment_the_user_comes_back(): void
    {
        $user = $this->inactiveUser(8);
        $this->dispatchNow();

        $step2 = LifecycleEmail::where('key', 'reengagement.step_2')->first();
        $log = LifecycleEmailLog::create([
            'user_id' => $user->id, 'lifecycle_email_id' => $step2->id, 'type' => 'reengagement',
            'occurrence' => LifecycleEmailDispatcher::spellOccurrence($user),
        ]);

        $user->forceFill(['last_active_at' => now()])->save();
        (new SendLifecycleEmail($log->id))->handle(app(SamAgentService::class));

        $this->assertSame(LifecycleEmailLog::STATUS_SKIPPED, $log->fresh()->status);
        $this->assertSame('The user became active again.', $log->fresh()->error);
        Mail::assertSent(LifecycleMail::class, 1);
    }

    public function test_a_new_quiet_spell_starts_the_sequence_again(): void
    {
        $user = $this->inactiveUser(8);
        $this->dispatchNow();

        $user->forceFill(['last_active_at' => now()])->save();
        $this->travel(8)->days();
        $this->dispatchNow();

        $this->assertSame(2, LifecycleEmailLog::where('user_id', $user->id)->where('status', 'sent')->whereHas('lifecycleEmail', fn ($q) => $q->where('key', 'reengagement.step_1'))->count());
    }

    public function test_users_who_never_logged_in_count_from_signup(): void
    {
        $user = User::factory()->create(['created_at' => now()->subDays(10)]);

        $this->dispatchNow();

        $this->assertSame(['reengagement.step_1'], $this->stepsSentTo($user));
    }

    public function test_seats_affiliates_staff_suspended_and_opted_out_accounts_are_skipped(): void
    {
        $this->seed(RolesSeeder::class);
        $owner = $this->inactiveUser(8);
        $this->inactiveUser(8, ['agency_owner_id' => $owner->id]);
        $this->inactiveUser(8, ['is_affiliate_only' => true]);
        $this->inactiveUser(8, ['is_suspended' => true]);
        $this->inactiveUser(8, ['marketing_emails_opt_in' => false]);
        $this->inactiveUser(8)->assignRole('support');

        $this->assertSame(1, $this->dispatchNow());
        Mail::assertSent(LifecycleMail::class, fn (LifecycleMail $mail) => $mail->hasTo($owner->email));
    }

    public function test_it_respects_the_send_hour(): void
    {
        $this->inactiveUser(8, ['timezone' => 'America/New_York']);

        $this->assertSame(0, $this->dispatchNow());
    }

    public function test_disabled_steps_and_the_master_switch_are_respected(): void
    {
        $this->inactiveUser(8);
        LifecycleEmail::where('key', 'reengagement.step_1')->update(['enabled' => false]);

        $this->assertSame(0, $this->dispatchNow());

        LifecycleEmail::where('key', 'reengagement.step_1')->update(['enabled' => true]);
        LifecycleSettings::save(['reengagement_enabled' => false]);
        $this->assertSame(0, $this->dispatchNow());
    }
}
