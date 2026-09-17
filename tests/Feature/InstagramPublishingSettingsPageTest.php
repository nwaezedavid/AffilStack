<?php

namespace Tests\Feature;

use App\Filament\Pages\InstagramPublishingSettings;
use App\Models\InstagramSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class InstagramPublishingSettingsPageTest extends TestCase
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

    public function test_admin_can_save_and_enable_instagram_publishing(): void
    {
        Livewire::actingAs($this->admin)
            ->test(InstagramPublishingSettings::class)
            ->fillForm([
                'is_enabled' => true,
                'approval_status' => 'approved',
                'app_id' => 'app123',
                'app_secret' => 'super-secret-value',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = InstagramSetting::current();
        $this->assertTrue($settings->is_enabled);
        $this->assertSame('app123', $settings->credential('app_id'));
        $this->assertTrue($settings->isAvailable());
        $this->assertTrue($settings->isApprovedForPublishing());

        $raw = \DB::table('instagram_settings')->where('id', 1)->value('credentials');
        $this->assertStringNotContainsString('super-secret-value', (string) $raw);
    }

    public function test_a_department_scoped_sub_account_without_content_access_cannot_reach_the_page(): void
    {
        $subAccount = User::factory()->create();
        $subAccount->assignRole('admin_sub');
        $subAccount->syncPermissions([]);

        $this->actingAs($subAccount);
        $this->assertFalse(InstagramPublishingSettings::canAccess());

        $subAccount->syncPermissions(['department.content']);
        $this->assertTrue(InstagramPublishingSettings::canAccess());
    }
}
