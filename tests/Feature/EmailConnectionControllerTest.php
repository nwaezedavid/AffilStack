<?php

namespace Tests\Feature;

use App\Models\EmailConnection;
use App\Models\GoogleOauthSetting;
use App\Models\Offer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Crm\PersonalEmailSender;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Task #1: the dashboard side of connecting Gmail/SMTP for CRM nurture
 * sending. Owner-only — see config('agency.seat_allowed_routes'), which
 * deliberately excludes every CRM-related route including this one.
 */
class EmailConnectionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->user = User::factory()->create();
        $this->user->assignRole('user');
    }

    protected function enableGmailSending(): void
    {
        GoogleOauthSetting::current()->update([
            'is_enabled' => true,
            'gmail_sending_enabled' => true,
            'credentials' => ['client_id' => 'abc.apps.googleusercontent.com', 'client_secret' => 'secret'],
        ]);
    }

    public function test_the_page_reflects_whether_gmail_connecting_is_available(): void
    {
        $this->actingAs($this->user)
            ->get(route('email-connections.index'))
            ->assertOk()
            ->assertSee("Gmail connections aren't available yet", false);

        $this->enableGmailSending();

        $this->actingAs($this->user)
            ->get(route('email-connections.index'))
            ->assertOk()
            ->assertSee('Connect Gmail');
    }

    public function test_redirecting_to_google_fails_gracefully_when_not_available(): void
    {
        $this->actingAs($this->user)
            ->get(route('email-connections.gmail.redirect'))
            ->assertRedirect()
            ->assertSessionHas('error');
    }

    public function test_redirecting_to_google_sends_the_user_to_googles_consent_screen(): void
    {
        $this->enableGmailSending();

        $response = $this->actingAs($this->user)->get(route('email-connections.gmail.redirect'));

        $response->assertRedirect();
        $this->assertStringContainsString('accounts.google.com', $response->headers->get('Location'));
    }

    public function test_the_callback_creates_a_verified_gmail_connection(): void
    {
        $this->enableGmailSending();
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'at1', 'refresh_token' => 'rt1', 'expires_in' => 3600]),
            'www.googleapis.com/oauth2/v3/userinfo' => Http::response(['email' => 'sender@gmail.com']),
        ]);

        // Establish session state the way redirectToGoogle() would.
        $this->actingAs($this->user)->get(route('email-connections.gmail.redirect'));

        $state = session('gmail_connect_state');

        $response = $this->actingAs($this->user)
            ->get(route('email-connections.gmail.callback', ['state' => $state, 'code' => 'auth-code']));

        $response->assertRedirect(route('email-connections.index'));
        $response->assertSessionHas('success');

        $connection = EmailConnection::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('gmail', $connection->provider);
        $this->assertSame('sender@gmail.com', $connection->connected_email);
        $this->assertTrue($connection->isVerified());
    }

    public function test_the_callback_rejects_a_mismatched_state(): void
    {
        $this->enableGmailSending();
        $this->actingAs($this->user)->get(route('email-connections.gmail.redirect'));

        $response = $this->actingAs($this->user)
            ->get(route('email-connections.gmail.callback', ['state' => 'wrong-state', 'code' => 'auth-code']));

        $response->assertRedirect(route('email-connections.index'));
        $response->assertSessionHas('error');
        $this->assertDatabaseCount('email_connections', 0);
    }

    public function test_storing_smtp_settings_creates_a_verified_connection_and_sends_a_test_email(): void
    {
        $this->mock(PersonalEmailSender::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->withArgs(function ($connection, $to, $subject) {
                return $to === $this->user->email;
            });
        });

        $response = $this->actingAs($this->user)->post(route('email-connections.smtp.store'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'smtpuser',
            'password' => 'smtppass',
            'from_email' => 'me@mydomain.com',
            'from_name' => 'Me',
        ]);

        $response->assertRedirect(route('email-connections.index'));
        $response->assertSessionHas('success');

        $connection = EmailConnection::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('smtp', $connection->provider);
        $this->assertSame('me@mydomain.com', $connection->connected_email);
        $this->assertTrue($connection->isVerified());
        $this->assertSame('smtppass', $connection->credential('password'));

        $raw = \DB::table('email_connections')->where('user_id', $this->user->id)->value('credentials');
        $this->assertStringNotContainsString('smtppass', (string) $raw);
    }

    public function test_storing_smtp_settings_records_a_failed_verification_when_the_test_email_fails(): void
    {
        $this->mock(PersonalEmailSender::class, function (MockInterface $mock) {
            $mock->shouldReceive('send')->once()->andThrow(new RuntimeException('Connection refused'));
        });

        $response = $this->actingAs($this->user)->post(route('email-connections.smtp.store'), [
            'host' => 'smtp.example.com',
            'port' => 587,
            'username' => 'smtpuser',
            'password' => 'smtppass',
            'from_email' => 'me@mydomain.com',
        ]);

        $response->assertRedirect(route('email-connections.index'));
        $response->assertSessionHas('error');

        $connection = EmailConnection::where('user_id', $this->user->id)->firstOrFail();
        $this->assertSame('failed', $connection->verification_status);
        $this->assertFalse($connection->isVerified());
    }

    public function test_a_user_can_disconnect_their_email_connection(): void
    {
        EmailConnection::create([
            'user_id' => $this->user->id,
            'provider' => 'smtp',
            'credentials' => ['host' => 'smtp.example.com'],
            'connected_email' => 'me@mydomain.com',
            'verification_status' => 'success',
        ]);

        $this->actingAs($this->user)
            ->delete(route('email-connections.destroy'))
            ->assertRedirect(route('email-connections.index'));

        $this->assertDatabaseCount('email_connections', 0);
    }

    public function test_a_team_seat_cannot_reach_any_email_connection_route(): void
    {
        $plan = Plan::factory()->create(['seat_mode' => 'isolated', 'team_seats' => 3]);
        $owner = User::factory()->create();
        $owner->assignRole('user');
        Subscription::factory()->create(['user_id' => $owner->id, 'plan_id' => $plan->id]);
        $offer = Offer::create(['user_id' => $owner->id, 'product_name' => 'A', 'product_url' => 'https://a.example', 'affiliate_network' => 'ShareASale', 'status' => 'ready']);
        $seat = User::factory()->create(['agency_owner_id' => $owner->id, 'seat_offer_id' => $offer->id]);
        $seat->assignRole('user');

        $this->actingAs($seat)->get(route('email-connections.index'))->assertForbidden();
    }
}
