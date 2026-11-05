<?php

namespace Tests\Feature;

use App\Filament\Pages\AppleSignInSettings;
use App\Models\AppleOauthSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin: Site > Apple Login — the Apple keys are entered here, encrypted,
 * and the private key is never shown back.
 */
class AppleSignInSettingsPageTest extends TestCase
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

    protected function pem(): string
    {
        openssl_pkey_export(openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']), $pem);

        return $pem;
    }

    public function test_admin_saves_the_apple_keys_encrypted(): void
    {
        $pem = $this->pem();

        Livewire::actingAs($this->admin)
            ->test(AppleSignInSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'services_id' => 'com.affilstack.signin',
                'team_id' => 'teamid1234',
                'key_id' => 'keyid12345',
                'private_key' => $pem,
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('Apple login settings saved');

        $settings = AppleOauthSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('TEAMID1234', $settings->credential('team_id'));
        $this->assertSame(trim($pem), $settings->credential('private_key'));
        $this->assertStringNotContainsString('PRIVATE KEY', (string) $settings->getRawOriginal('credentials'));
    }

    public function test_the_private_key_is_never_shown_and_blank_keeps_it(): void
    {
        $pem = $this->pem();
        AppleOauthSetting::current()->update(['credentials' => ['services_id' => 'com.affilstack.signin', 'team_id' => 'TEAMID1234', 'key_id' => 'KEYID12345', 'private_key' => trim($pem)]]);

        Livewire::actingAs($this->admin)
            ->test(AppleSignInSettings::class)
            ->assertFormSet(['private_key' => null, 'team_id' => 'TEAMID1234'])
            ->assertDontSee('BEGIN PRIVATE KEY')
            ->fillForm(['private_key' => '', 'services_id' => 'com.affilstack.web'])
            ->call('save');

        $this->assertSame(trim($pem), AppleOauthSetting::current()->credential('private_key'));
        $this->assertSame('com.affilstack.web', AppleOauthSetting::current()->credential('services_id'));
    }

    public function test_check_keys_accepts_a_real_key_and_rejects_junk(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AppleSignInSettings::class)
            ->fillForm(['services_id' => 'com.affilstack.signin', 'team_id' => 'TEAMID1234', 'key_id' => 'KEYID12345', 'private_key' => $this->pem()])
            ->call('verify')
            ->assertNotified('Looks good');

        Livewire::actingAs($this->admin)
            ->test(AppleSignInSettings::class)
            ->fillForm(['private_key' => "-----BEGIN PRIVATE KEY-----\nnot-a-key\n-----END PRIVATE KEY-----"])
            ->call('verify')
            ->assertNotified('Check your keys');
    }
}
