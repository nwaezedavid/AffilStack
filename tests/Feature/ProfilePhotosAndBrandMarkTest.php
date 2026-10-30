<?php

namespace Tests\Feature;

use App\Filament\Pages\Auth\EditProfile;
use App\Models\SiteSetting;
use App\Models\User;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Profile photos for partners and admins (instead of initials), and the
 * uploaded logo everywhere — including the mobile menu — instead of the
 * two-letter placeholder.
 */
class ProfilePhotosAndBrandMarkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        Storage::fake('public');
    }

    public function test_a_partner_uploads_and_removes_a_profile_photo(): void
    {
        $partner = User::factory()->create(['is_affiliate_only' => true]);
        $partner->assignRole('user');

        $this->actingAs($partner)
            ->post(route('partner.account.photo'), ['photo' => UploadedFile::fake()->image('me.png', 200, 200)])
            ->assertSessionHasNoErrors();

        $path = $partner->fresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);
        $this->actingAs($partner)->get(route('partner.account'))->assertSee(Storage::disk('public')->url($path), false);

        $this->actingAs($partner)->delete(route('partner.account.photo.destroy'));

        $this->assertNull($partner->fresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_the_admin_panel_shows_the_admins_photo(): void
    {
        $admin = User::factory()->create(['avatar_path' => 'avatars/1-abc.webp']);

        $this->assertSame(Storage::disk('public')->url('avatars/1-abc.webp'), $admin->getFilamentAvatarUrl());
        $this->assertNull(User::factory()->create()->getFilamentAvatarUrl());
    }

    public function test_an_admin_can_open_the_profile_page(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(['admin', 'super-admin']);

        Livewire::actingAs($admin)->test(EditProfile::class)->assertOk()->assertSee('Profile photo');
    }

    public function test_the_mobile_menu_and_header_use_the_uploaded_logo(): void
    {
        SiteSetting::set('site_name', 'AffilStack');
        SiteSetting::set('logo_rectangular_path', 'brand/logo.png');

        $html = $this->get(route('home'))->assertOk()->getContent();
        $logoUrl = Storage::disk('public')->url('brand/logo.png');

        $this->assertGreaterThanOrEqual(2, substr_count($html, 'src="'.$logoUrl.'"'));
        $this->assertStringNotContainsString('aria-hidden="true">AF</span>', $html);
    }

    public function test_the_square_icon_is_used_when_there_is_no_full_logo(): void
    {
        SiteSetting::set('site_name', 'AffilStack');
        SiteSetting::set('logo_square_path', 'brand/icon.png');

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(Storage::disk('public')->url('brand/icon.png'), $html);
        $this->assertStringNotContainsString('>AF</span>', $html);
    }
}
