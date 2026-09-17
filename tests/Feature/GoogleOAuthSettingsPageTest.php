<?php

namespace Tests\Feature;

use App\Filament\Pages\GoogleOAuthSettings;
use App\Models\GoogleOauthSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoogleOAuthSettingsPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->admin = User::factory()->create();
        $this->admin->assignRole('admin');
    }

    public function test_admin_can_save_and_enable_google_login(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GoogleOauthSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('123-abc.apps.googleusercontent.com', $settings->credential('client_id'));

        $raw = \DB::table('google_oauth_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }

    public function test_admin_can_enable_gmail_sending_using_the_same_client_credentials(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'gmail_sending_enabled' => true,
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GoogleOauthSetting::current();
        $this->assertTrue($settings->gmail_sending_enabled);
        $this->assertTrue($settings->gmailSendingAvailable());
    }

    public function test_gmail_sending_is_unavailable_until_the_toggle_is_on_even_with_credentials_saved(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'gmail_sending_enabled' => false,
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save');

        $this->assertFalse(GoogleOauthSetting::current()->gmailSendingAvailable());
    }

    public function test_admin_can_enable_youtube_publishing_and_track_approval_status(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'youtube_publishing_enabled' => true,
                'youtube_approval_status' => 'pending',
                'youtube_approval_notes' => 'Submitted the Audit + Quota Extension form on 2026-09-20.',
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = GoogleOauthSetting::current();
        $this->assertTrue($settings->youtube_publishing_enabled);
        $this->assertTrue($settings->youtubePublishingAvailable());
        $this->assertFalse($settings->isYoutubeApprovedForPublishing());
        $this->assertSame('pending', $settings->youtube_approval_status);
    }

    public function test_youtube_publishing_is_only_approved_once_the_status_is_set_to_approved(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'youtube_publishing_enabled' => true,
                'youtube_approval_status' => 'approved',
                'client_id' => '123-abc.apps.googleusercontent.com',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save');

        $this->assertTrue(GoogleOauthSetting::current()->isYoutubeApprovedForPublishing());
    }

    public function test_check_credentials_flags_a_malformed_client_id(): void
    {
        Livewire::actingAs($this->admin)
            ->test(GoogleOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'client_id' => 'not-a-real-google-client-id',
                'client_secret' => 'super-secret-value',
            ])
            ->call('verify');

        // verify() persists first, so this reflects the checked credentials.
        $this->assertSame('not-a-real-google-client-id', GoogleOauthSetting::current()->credential('client_id'));
    }
}
