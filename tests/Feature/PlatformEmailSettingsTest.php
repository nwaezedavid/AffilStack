<?php

namespace Tests\Feature;

use App\Filament\Pages\PlatformEmailSettings;
use App\Models\PlatformMailSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Symfony\Component\Mailer\Exception\TransportException;
use Tests\TestCase;

/**
 * AffilStack's own sending mailbox can be set in Admin → Site → Platform
 * Email, and a mail server failure on "Forgot password" shows a message on
 * the form instead of a 500 error.
 */
class PlatformEmailSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_saved_settings_override_the_env_mail_config(): void
    {
        PlatformMailSetting::current()->update([
            'is_enabled' => true,
            'credentials' => ['host' => 'smtp.example.com', 'port' => '587', 'username' => 'mailer@example.com', 'password' => 'secret-pass', 'from_name' => 'AffilStack'],
        ]);

        PlatformMailSetting::applyToConfig();

        $this->assertSame('smtp.example.com', config('mail.mailers.smtp.host'));
        $this->assertSame(587, config('mail.mailers.smtp.port'));
        $this->assertSame('smtp', config('mail.mailers.smtp.scheme'));
        $this->assertSame('secret-pass', config('mail.mailers.smtp.password'));
        $this->assertSame('mailer@example.com', config('mail.from.address'));
    }

    public function test_switched_off_settings_leave_the_env_in_charge(): void
    {
        $before = config('mail.mailers.smtp.host');

        PlatformMailSetting::current()->update([
            'is_enabled' => false,
            'credentials' => ['host' => 'smtp.example.com', 'username' => 'mailer@example.com', 'password' => 'secret-pass'],
        ]);
        PlatformMailSetting::applyToConfig();

        $this->assertSame($before, config('mail.mailers.smtp.host'));
    }

    public function test_the_admin_page_saves_without_exposing_the_password(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);

        Livewire::actingAs($admin)
            ->test(PlatformEmailSettings::class)
            ->fillForm(['is_enabled' => true, 'host' => 'smtp.hostinger.com', 'port' => '465', 'username' => 'noreply@example.com', 'password' => 'new-mailbox-pass', 'from_address' => 'noreply@example.com', 'from_name' => 'AffilStack'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('new-mailbox-pass', PlatformMailSetting::current()->credential('password'));

        Livewire::actingAs($admin)
            ->test(PlatformEmailSettings::class)
            ->assertDontSee('new-mailbox-pass')
            ->fillForm(['password' => ''])
            ->call('save');

        $this->assertSame('new-mailbox-pass', PlatformMailSetting::current()->credential('password'));
    }

    public function test_a_mail_server_failure_on_forgot_password_shows_a_message_not_a_500(): void
    {
        $user = User::factory()->create(['email' => 'jane@example.com']);
        Notification::shouldReceive('send')->andThrow(new TransportException('Expected response code "235" but got code "535"'));
        Notification::shouldReceive('sendNow')->andThrow(new TransportException('Expected response code "235" but got code "535"'));

        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => $user->email])
            ->assertRedirect(route('password.request'))
            ->assertSessionHasErrors('email');
    }

    public function test_forgot_password_still_sends_the_reset_email_normally(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPassword::class);
    }
}
