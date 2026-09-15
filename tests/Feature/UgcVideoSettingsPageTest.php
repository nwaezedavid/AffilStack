<?php

namespace Tests\Feature;

use App\Filament\Pages\UgcVideoSettings;
use App\Models\HeyGenSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UgcVideoSettingsPageTest extends TestCase
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

    public function test_admin_can_save_a_heygen_api_key_encrypted_at_rest(): void
    {
        Livewire::actingAs($this->admin)
            ->test(UgcVideoSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'api_key' => 'super-secret-heygen-key',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = HeyGenSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('super-secret-heygen-key', $settings->credential('api_key'));

        $raw = \DB::table('heygen_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('super-secret-heygen-key', (string) $raw);
    }

    public function test_verify_action_records_a_successful_connection(): void
    {
        Http::fake(['api.heygen.com/v3/users/me' => Http::response(['data' => ['email' => 'a@b.com']], 200)]);

        Livewire::actingAs($this->admin)
            ->test(UgcVideoSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'api_key' => 'good-key',
            ])
            ->call('verify');

        $settings = HeyGenSetting::current();
        $this->assertSame('success', $settings->verification_status);
        $this->assertTrue($settings->isReady());
    }

    public function test_verify_action_records_a_failed_connection(): void
    {
        Http::fake(['api.heygen.com/v3/users/me' => Http::response(['error' => ['message' => 'Invalid API key']], 401)]);

        Livewire::actingAs($this->admin)
            ->test(UgcVideoSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'api_key' => 'bad-key',
            ])
            ->call('verify');

        $settings = HeyGenSetting::current();
        $this->assertSame('failed', $settings->verification_status);
        $this->assertFalse($settings->isReady());
    }
}
