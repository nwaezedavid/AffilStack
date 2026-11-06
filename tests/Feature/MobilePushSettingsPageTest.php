<?php

namespace Tests\Feature;

use App\Filament\Pages\MobilePushSettings;
use App\Filament\Resources\MobileDevices\Pages\ListMobileDevices;
use App\Models\MobileDevice;
use App\Models\MobileIntegrationSetting;
use App\Models\User;
use App\Services\Mobile\Push\ExpoPushClient;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Admin: Site > App Push & Links and System > App Devices.
 */
class MobilePushSettingsPageTest extends TestCase
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

    protected function fingerprint(string $pair = 'ab'): string
    {
        return implode(':', array_fill(0, 32, $pair));
    }

    protected function fakeExpo(): void
    {
        Http::fake([
            ExpoPushClient::SEND_ENDPOINT => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]]),
            ExpoPushClient::RECEIPTS_ENDPOINT => Http::response(['data' => []]),
        ]);
    }

    public function test_admin_saves_push_and_app_link_settings_with_the_token_encrypted(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MobilePushSettings::class)
            ->fillForm([
                'push_enabled' => true,
                'expo_access_token' => 'expo-secret-token',
                'ios_team_id' => 'a1b2c3d4e5',
                'android_sha256_fingerprints' => $this->fingerprint()."\n\n".$this->fingerprint('cd'),
            ])
            ->call('save')
            ->assertHasNoFormErrors()
            ->assertNotified('App push & links settings saved');

        $settings = MobileIntegrationSetting::current();
        $this->assertTrue($settings->push_enabled);
        $this->assertSame('expo-secret-token', $settings->credential('expo_access_token'));
        $this->assertStringNotContainsString('expo-secret-token', (string) $settings->getRawOriginal('credentials'));
        $this->assertSame('A1B2C3D4E5', $settings->ios_team_id);
        $this->assertSame([$this->fingerprint('AB'), $this->fingerprint('CD')], $settings->androidFingerprints());
    }

    public function test_the_token_is_never_shown_and_blank_keeps_it(): void
    {
        MobileIntegrationSetting::current()->update(['credentials' => ['expo_access_token' => 'expo-secret-token']]);

        Livewire::actingAs($this->admin)
            ->test(MobilePushSettings::class)
            ->assertFormSet(['expo_access_token' => null])
            ->assertDontSee('expo-secret-token')
            ->fillForm(['expo_access_token' => '', 'push_enabled' => true])
            ->call('save');

        $this->assertSame('expo-secret-token', MobileIntegrationSetting::current()->credential('expo_access_token'));
    }

    public function test_wrong_ids_are_refused(): void
    {
        Livewire::actingAs($this->admin)
            ->test(MobilePushSettings::class)
            ->fillForm(['ios_team_id' => 'short', 'android_sha256_fingerprints' => 'not-a-fingerprint'])
            ->call('save')
            ->assertHasFormErrors(['ios_team_id', 'android_sha256_fingerprints']);

        $this->assertNull(MobileIntegrationSetting::current()->ios_team_id);
    }

    public function test_the_test_button_sends_to_the_admins_own_phone(): void
    {
        $this->fakeExpo();
        MobileIntegrationSetting::current()->update(['push_enabled' => true]);

        Livewire::actingAs($this->admin)
            ->test(MobilePushSettings::class)
            ->callAction('sendTest')
            ->assertNotified('No phone to send to');

        Http::assertNothingSent();

        MobileDevice::factory()->for($this->admin)->withPush('ExponentPushToken[admin-phone]')->create();

        Livewire::actingAs($this->admin)
            ->test(MobilePushSettings::class)
            ->callAction('sendTest')
            ->assertNotified('Sent to 1 of 1 phone(s)');

        Http::assertSent(fn (HttpRequest $request): bool => $request->url() === ExpoPushClient::SEND_ENDPOINT && $request->data()[0]['to'] === 'ExponentPushToken[admin-phone]');
    }

    public function test_the_devices_list_shows_signed_in_phones_and_can_test_or_sign_one_out(): void
    {
        $this->fakeExpo();
        MobileIntegrationSetting::current()->update(['push_enabled' => true]);
        $phone = MobileDevice::factory()->withPush()->create(['name' => 'Ada\'s iPhone']);
        $old = MobileDevice::factory()->revoked()->create();

        Livewire::actingAs($this->admin)
            ->test(ListMobileDevices::class)
            ->assertCanSeeTableRecords([$phone])
            ->assertCanNotSeeTableRecords([$old])
            ->callTableAction('sendTest', $phone)
            ->assertNotified('Test notification sent')
            ->callTableAction('signOut', $phone);

        $this->assertFalse($phone->fresh()->isActive());
        $this->assertNull($phone->fresh()->push_token);
    }
}
