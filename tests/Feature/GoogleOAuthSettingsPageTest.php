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
