<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivityDay;
use App\Support\Lifecycle\LifecycleSettings;
use App\Support\Lifecycle\UserActivity;
use Illuminate\Auth\Events\Login;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class UserActivityTrackingTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_authenticated_request_records_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('profile'))->assertOk();

        $this->assertNotNull($user->fresh()->last_active_at);
        $this->assertTrue(UserActivityDay::where('user_id', $user->id)->whereDate('date', today())->exists());
    }

    public function test_activity_writes_are_throttled(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['last_active_at' => now()->subMinutes(2)])->save();
        $before = $user->fresh()->last_active_at;

        $this->actingAs($user->fresh())->get(route('profile'));
        $this->assertTrue($before->equalTo($user->fresh()->last_active_at));

        $this->travel(10)->minutes();
        $this->actingAs($user->fresh())->get(route('profile'));
        $this->assertTrue($user->fresh()->last_active_at->greaterThan($before));
    }

    public function test_background_notification_polling_does_not_count_as_activity(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson(route('notifications.poll'))->assertOk();

        $this->assertNull($user->fresh()->last_active_at);
    }

    public function test_guests_are_ignored(): void
    {
        $this->get('/login')->assertOk();

        $this->assertSame(0, UserActivityDay::count());
    }

    public function test_every_login_stamps_last_login_at(): void
    {
        $user = User::factory()->create();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
        $this->assertNotNull($user->fresh()->last_active_at);
    }

    public function test_programmatic_logins_such_as_google_sign_in_are_recorded_too(): void
    {
        $user = User::factory()->create();

        Auth::login($user);

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_the_login_listener_is_wired_to_the_login_event(): void
    {
        $user = User::factory()->create();

        event(new Login('web', $user, false));

        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_activity_status_uses_configurable_thresholds(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserActivity::NEVER, $user->activityStatus());

        $user->forceFill(['last_active_at' => now()->subDays(3)])->save();
        $this->assertSame(UserActivity::ACTIVE, $user->fresh()->activityStatus());

        $user->forceFill(['last_active_at' => now()->subDays(10)])->save();
        $this->assertSame(UserActivity::IDLE, $user->fresh()->activityStatus());

        $user->forceFill(['last_active_at' => now()->subDays(40)])->save();
        $this->assertSame(UserActivity::INACTIVE, $user->fresh()->activityStatus());

        LifecycleSettings::save(['active_within_days' => 14, 'inactive_after_days' => 60]);
        $this->assertSame(UserActivity::IDLE, $user->fresh()->activityStatus());

        $user->forceFill(['last_active_at' => null, 'last_login_at' => now()->subDays(5)])->save();
        $this->assertSame(UserActivity::ACTIVE, $user->fresh()->activityStatus());
    }
}
