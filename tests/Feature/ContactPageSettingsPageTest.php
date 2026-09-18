<?php

namespace Tests\Feature;

use App\Filament\Pages\ContactPageSettings;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The admin "Contact Page" Filament page (task #162): the contact image
 * and the two extra front-door emails (support_email itself stays owned
 * by Brand Settings — see ContactPageSettings' docblock).
 */
class ContactPageSettingsPageTest extends TestCase
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

    public function test_admin_can_save_contact_page_settings(): void
    {
        Livewire::actingAs($this->admin)
            ->test(ContactPageSettings::class)
            ->fillForm([
                'contact_email_hello' => 'hello@custom-domain.com',
                'contact_email_info' => 'info@custom-domain.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('hello@custom-domain.com', SiteSetting::get('contact_email_hello'));
        $this->assertSame('info@custom-domain.com', SiteSetting::get('contact_email_info'));
    }
}
