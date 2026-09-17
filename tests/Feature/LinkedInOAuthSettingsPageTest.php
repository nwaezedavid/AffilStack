<?php

namespace Tests\Feature;

use App\Filament\Pages\LinkedInOAuthSettings;
use App\Models\LinkedInOauthSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class LinkedInOAuthSettingsPageTest extends TestCase
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

    public function test_admin_can_save_and_enable_linkedin_connect(): void
    {
        Livewire::actingAs($this->admin)
            ->test(LinkedInOAuthSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'client_id' => 'client123',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = LinkedInOauthSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('client123', $settings->credential('client_id'));
        $this->assertTrue($settings->isAvailable());

        $raw = \DB::table('linkedin_oauth_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }

    public function test_a_department_scoped_sub_account_without_site_access_cannot_reach_the_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(LinkedInOAuthSettings::canAccess());

        $subAccount->syncPermissions(['department.site']);
        $this->assertTrue(LinkedInOAuthSettings::canAccess());
    }
}
