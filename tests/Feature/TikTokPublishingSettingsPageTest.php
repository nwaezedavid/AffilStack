<?php

namespace Tests\Feature;

use App\Filament\Pages\TikTokPublishingSettings;
use App\Models\TikTokSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TikTokPublishingSettingsPageTest extends TestCase
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

    public function test_admin_can_save_and_enable_tiktok_publishing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(TikTokPublishingSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'approval_status' => 'approved',
                'client_key' => 'key123',
                'client_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = TikTokSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('key123', $settings->credential('client_key'));
        $this->assertTrue($settings->isAvailable());
        $this->assertTrue($settings->isApprovedForPublishing());

        $raw = \DB::table('tiktok_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(TikTokPublishingSettings::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(TikTokPublishingSettings::canAccess());
    }
}
