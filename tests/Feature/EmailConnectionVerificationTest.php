<?php

namespace Tests\Feature;

use App\Models\CrmCampaign;
use App\Models\CrmContact;
use App\Models\CrmList;
use App\Models\EmailConnection;
use App\Models\User;
use App\Notifications\CrmCampaignPaused;
use App\Services\Crm\CampaignMergeFields;
use App\Services\Crm\ConnectionErrorExplainer;
use App\Services\Crm\Imap\ImapTransport;
use App\Services\Crm\PersonalEmailSender;
use App\Services\Crm\SmtpProbe;
use App\Support\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Mockery\MockInterface;
use RuntimeException;
use Tests\Support\CrmTestHelpers;
use Tests\Support\ScriptedImapTransport;
use Tests\TestCase;

/**
 * The Email Sending page's live checks: real SMTP AUTH (probe faked) and
 * IMAP LOGIN + SELECT (scripted server), friendly error explanations, the
 * daily re-check, limits/footer settings, and the merge-field renderer.
 */
class EmailConnectionVerificationTest extends TestCase
{
    use CrmTestHelpers, RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->crmUser();
    }

    protected function imapServer(bool $loginOk = true): ScriptedImapTransport
    {
        return new ScriptedImapTransport([
            '/ LOGIN /' => $loginOk ? "{TAG} OK Logged in\r\n" : "{TAG} NO [AUTHENTICATIONFAILED] Authentication failed.\r\n",
            '/ SELECT "INBOX"/' => "* 1284 EXISTS\r\n* OK [UIDVALIDITY 99] ok\r\n* OK [UIDNEXT 2000] ok\r\n{TAG} OK [READ-WRITE] done\r\n",
            '/ LOGOUT/' => "* BYE\r\n{TAG} OK bye\r\n",
        ]);
    }

    public function test_the_page_shows_verified_and_failed_states(): void
    {
        $connection = $this->verifiedSmtp($this->user, ['verification_message' => 'Logged in to smtp.hostinger.com successfully.']);

        $this->actingAs($this->user)->get(route('email-connections.index'))
            ->assertOk()
            ->assertSee('Verified')
            ->assertSee('Logged in to smtp.hostinger.com successfully.')
            ->assertSee('Hostinger Email')
            ->assertSee('Inbox reading (IMAP)');

        $connection->update(['verification_status' => 'failed', 'verification_message' => 'Gmail rejected the password — use a 16-character App Password']);

        $this->actingAs($this->user->fresh())->get(route('email-connections.index'))
            ->assertOk()
            ->assertSee('Check failed')
            ->assertSee('16-character App Password');
    }

    public function test_a_gmail_auth_failure_is_explained_in_plain_words(): void
    {
        $this->mock(SmtpProbe::class, fn (MockInterface $mock) => $mock->shouldReceive('probe')->andThrow(new RuntimeException('Expected response code "235" but got code "535", with message "535-5.7.8 Username and Password not accepted."')));

        $this->actingAs($this->user)->post(route('email-connections.smtp.store'), [
            'preset' => 'gmail', 'host' => 'smtp.gmail.com', 'port' => 465, 'encryption' => 'ssl',
            'username' => 'me@gmail.com', 'password' => 'my-normal-password', 'from_email' => 'me@gmail.com', 'send_test' => 0,
        ])->assertSessionHas('error');

        $connection = EmailConnection::firstOrFail();
        $this->assertSame('failed', $connection->verification_status);
        $this->assertStringContainsString('App Password', $connection->verification_message);
        $this->assertStringNotContainsString('535', $connection->verification_message);
        $this->assertSame('gmail', $connection->preset);
    }

    public function test_editing_smtp_without_a_password_keeps_the_stored_one(): void
    {
        $this->verifiedSmtp($this->user);
        $this->mock(SmtpProbe::class, function (MockInterface $mock) {
            $mock->shouldReceive('probe')->once()->withArgs(fn (array $settings) => $settings['password'] === 'secret' && $settings['port'] === 587);
        });

        $this->actingAs($this->user)->post(route('email-connections.smtp.store'), [
            'host' => 'smtp.hostinger.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'me@mybiz.example',
            'password' => '', 'from_email' => 'me@mybiz.example', 'send_test' => 0,
        ])->assertSessionHas('success');

        $this->assertSame('secret', EmailConnection::first()->credential('password'));
    }

    public function test_smtp_hosts_on_private_networks_are_refused(): void
    {
        OutboundUrlGuard::resolveUsing(fn () => ['10.0.0.5']);

        $this->actingAs($this->user)->post(route('email-connections.smtp.store'), [
            'host' => 'internal.example', 'port' => 587, 'username' => 'a', 'password' => 'b', 'from_email' => 'a@b.example',
        ])->assertSessionHasErrors('host');
    }

    public function test_imap_settings_are_verified_with_a_real_login_and_select(): void
    {
        $this->verifiedSmtp($this->user);
        $server = $this->imapServer();
        $this->app->instance(ImapTransport::class, $server);

        $this->actingAs($this->user)->post(route('email-connections.imap.store'), [
            'host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl', 'same_as_smtp' => 1,
        ])->assertSessionHas('success');

        $connection = EmailConnection::first();
        $this->assertSame('success', $connection->imap_status);
        $this->assertStringContainsString('1,284 messages', $connection->imap_message);
        $this->assertSame(99, (int) $connection->imap_uidvalidity);
        $this->assertSame('me@mybiz.example', $connection->imapCredential('username'));
        $this->assertSame('A0001 LOGIN "me@mybiz.example" "secret"', $server->commands[0]);
        $this->assertSame('imap', $connection->replyDetectionMethod());

        $raw = \DB::table('email_connections')->value('imap_credentials');
        $this->assertStringNotContainsString('secret', (string) $raw);

        $this->actingAs($this->user)->get(route('email-connections.index'))->assertSee('Watching inbox');
    }

    public function test_a_failed_imap_login_is_reported(): void
    {
        $this->verifiedSmtp($this->user);
        $this->app->instance(ImapTransport::class, $this->imapServer(loginOk: false));

        $this->actingAs($this->user)->post(route('email-connections.imap.store'), [
            'host' => 'imap.zoho.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@zoho.example', 'password' => 'wrong',
        ])->assertSessionHas('error');

        $connection = EmailConnection::first();
        $this->assertSame('failed', $connection->imap_status);
        $this->assertStringContainsString('rejected the username or password', $connection->imap_message);
        $this->assertNull($connection->replyDetectionMethod());
    }

    public function test_reply_detection_can_be_turned_off(): void
    {
        $this->verifiedSmtp($this->user, ['imap_credentials' => ['host' => 'imap.x.example', 'username' => 'u', 'password' => 'p'], 'imap_status' => 'success']);

        $this->actingAs($this->user)->delete(route('email-connections.imap.destroy'))->assertSessionHas('success');

        $this->assertFalse(EmailConnection::first()->hasImap());
    }

    public function test_test_connection_reruns_every_check_and_can_email_the_user(): void
    {
        $this->verifiedSmtp($this->user, [
            'verification_status' => 'failed',
            'imap_credentials' => ['host' => 'imap.hostinger.com', 'port' => 993, 'encryption' => 'ssl', 'username' => 'me@mybiz.example', 'password' => 'secret'],
            'imap_status' => 'failed',
        ]);
        $this->mock(SmtpProbe::class, fn (MockInterface $mock) => $mock->shouldReceive('probe')->once());
        $this->mock(PersonalEmailSender::class, fn (MockInterface $mock) => $mock->shouldReceive('send')->once()->withArgs(fn ($c, $to) => $to === $this->user->email));
        $this->app->instance(ImapTransport::class, $this->imapServer());

        $this->actingAs($this->user)->post(route('email-connections.test'), ['send_test' => 1])->assertSessionHas('success');

        $connection = EmailConnection::first();
        $this->assertTrue($connection->isVerified());
        $this->assertSame('success', $connection->imap_status);
        $this->assertNotNull($connection->last_checked_at);
    }

    public function test_sending_limits_and_footer_are_saved_within_the_provider_cap(): void
    {
        $this->verifiedSmtp($this->user);

        $this->actingAs($this->user)->patch(route('email-connections.settings'), ['daily_limit' => 100000])->assertSessionHasErrors('daily_limit');

        $this->actingAs($this->user)->patch(route('email-connections.settings'), [
            'daily_limit' => 40, 'from_name' => 'David', 'footer_business_name' => 'Prime Daddy', 'footer_address' => '14 Admiralty Way, Lagos',
        ])->assertSessionHas('success');

        $connection = EmailConnection::first();
        $this->assertSame(40, $connection->dailyCap());
        $this->assertSame('David', $connection->credential('from_name'));
        $this->assertSame('secret', $connection->credential('password'), 'Other credentials survive');
        $this->assertSame('14 Admiralty Way, Lagos', $connection->footer_address);
    }

    public function test_the_daily_recheck_pauses_running_campaigns_when_the_sender_breaks(): void
    {
        Notification::fake();
        $connection = $this->verifiedSmtp($this->user);
        $list = CrmList::factory()->create(['user_id' => $this->user->id]);
        $running = CrmCampaign::factory()->create(['user_id' => $this->user->id, 'crm_list_id' => $list->id, 'status' => 'sent', 'autopilot' => true]);
        $finished = CrmCampaign::factory()->create(['user_id' => $this->user->id, 'crm_list_id' => $list->id, 'status' => 'sent', 'autopilot' => false]);
        $this->mock(SmtpProbe::class, fn (MockInterface $mock) => $mock->shouldReceive('probe')->andThrow(new RuntimeException('Connection could not be established with host "smtp.hostinger.com:465": Connection timed out')));

        $this->artisan('crm:verify-email-connections')->assertSuccessful();

        $this->assertSame('failed', $connection->fresh()->verification_status);
        $this->assertStringContainsString('didn\'t answer in time', $connection->fresh()->verification_message);
        $this->assertSame('paused', $running->fresh()->status);
        $this->assertSame('sent', $finished->fresh()->status);
        Notification::assertSentTo($this->user, CrmCampaignPaused::class);
    }

    public function test_the_error_explainer_covers_common_failures(): void
    {
        $explain = app(ConnectionErrorExplainer::class);

        $this->assertStringContainsString('App Password', $explain->explain('535 Username and Password not accepted', 'smtp', 'smtp.gmail.com'));
        $this->assertStringContainsString('Authenticated SMTP', $explain->explain('535 5.7.139 Authentication unsuccessful', 'smtp', 'smtp.office365.com'));
        $this->assertStringContainsString('app password', $explain->explain('[AUTHENTICATIONFAILED] Invalid credentials', 'imap', 'imap.mail.yahoo.com'));
        $this->assertStringContainsString('TLS', $explain->explain('stream_socket_enable_crypto(): SSL operation failed', 'smtp', 'smtp.x.example'));
        $this->assertStringContainsString('typos', $explain->explain('The host smtp.typo.example could not be resolved.', 'smtp'));
        $this->assertStringContainsString('Reconnect Gmail', $explain->explain('invalid_grant', 'gmail'));
    }

    public function test_merge_fields_render_with_fallbacks_and_flag_typos(): void
    {
        $fields = app(CampaignMergeFields::class);
        $contact = new CrmContact(['name' => 'Joe\'s Bakery', 'company' => 'Joe\'s Bakery', 'location' => 'Austin, TX']);

        $text = $fields->render("Hi {first_name},\n\n{opening_line}\n\n{company} in {city} — see {website}.", $contact, null);

        $this->assertSame("Hi there,\n\nJoe's Bakery in Austin — see your website.", $text);
        $this->assertSame(['firstname'], $fields->unknownFields('Hi {firstname} at {company}'));
    }
}
