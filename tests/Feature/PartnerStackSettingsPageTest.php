<?php

namespace Tests\Feature;

use App\Filament\Pages\PartnerStackSettings;
use App\Models\PartnerStackSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PartnerStackSettingsPageTest extends TestCase
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

    public function test_admin_can_save_partnerstack_credentials_encrypted_at_rest(): void
    {
        Livewire::actingAs($this->admin)
            ->test(PartnerStackSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'public_key' => 'pk_test_123',
                'secret_key' => 'sk_test_super_secret',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = PartnerStackSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('sk_test_super_secret', $settings->credential('secret_key'));

        $raw = \DB::table('partner_stack_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('sk_test_super_secret', (string) $raw);
    }

    public function test_verify_action_records_a_successful_connection(): void
    {
        Http::fake(['api.partnerstack.com/v1/partnerships' => Http::response(['data' => []], 200)]);

        Livewire::actingAs($this->admin)
            ->test(PartnerStackSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'public_key' => 'pk_good',
                'secret_key' => 'sk_good',
            ])
            ->call('verify');

        $settings = PartnerStackSetting::current();
        $this->assertSame('success', $settings->verification_status);
        $this->assertTrue($settings->isConnected());
    }

    public function test_verify_action_records_a_failed_connection(): void
    {
        Http::fake(['api.partnerstack.com/v1/partnerships' => Http::response(['error' => 'unauthorized'], 401)]);

        Livewire::actingAs($this->admin)
            ->test(PartnerStackSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'public_key' => 'pk_bad',
                'secret_key' => 'sk_bad',
            ])
            ->call('verify');

        $settings = PartnerStackSetting::current();
        $this->assertSame('failed', $settings->verification_status);
        $this->assertFalse($settings->isConnected());
    }

    public function test_a_department_scoped_sub_account_without_billing_access_cannot_reach_the_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(PartnerStackSettings::canAccess());

        $subAccount->syncPermissions(['department.billing']);
        $this->assertTrue(PartnerStackSettings::canAccess());
    }
}
