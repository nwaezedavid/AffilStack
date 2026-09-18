<?php

namespace Tests\Feature;

use App\Filament\Pages\AboutPageSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin "About Page" Filament page (task #161): everything here is
 * plain SiteSetting fields, except the four Repeaters (goals, what we do,
 * how we work, who we serve), which round-trip through JSON exactly like
 * BrandSettings' menu_items field already does.
 */
class AboutPageSettingsPageTest extends TestCase
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

    public function test_admin_can_save_the_about_page_content(): void
    {
        Livewire::actingAs($this->admin)
            ->test(AboutPageSettings::class)
            ->fillForm([
                'about_hero_headline' => 'Custom headline',
                'about_mission' => 'Custom mission',
                'about_founder_name' => 'Ada Lovelace',
                'about_goals' => [
                    ['icon' => '🏆', 'title' => 'Custom goal', 'description' => 'A description.'],
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Custom headline', SiteSetting::get('about_hero_headline'));
        $this->assertSame('Custom mission', SiteSetting::get('about_mission'));
        $this->assertSame('Ada Lovelace', SiteSetting::get('about_founder_name'));
        $this->assertSame(
            [['icon' => '🏆', 'title' => 'Custom goal', 'description' => 'A description.']],
            json_decode(SiteSetting::get('about_goals'), true)
        );
    }

    public function test_saved_content_is_reloaded_on_mount(): void
    {
        SiteSetting::set('about_mission', 'Already saved mission');
        SiteSetting::set('about_goals', json_encode([['icon' => '🎯', 'title' => 'Existing goal', 'description' => 'Existing.']]));

        $component = Livewire::actingAs($this->admin)
            ->test(AboutPageSettings::class)
            ->assertFormSet(['about_mission' => 'Already saved mission']);

        // Repeater state is keyed by an internally-generated UUID rather than
        // a sequential index once loaded through the form, so this checks the
        // values reached the field instead of asserting an exact array shape.
        $this->assertSame(
            ['icon' => '🎯', 'title' => 'Existing goal', 'description' => 'Existing.'],
            array_values($component->get('data')['about_goals'])[0]
        );
    }
}
